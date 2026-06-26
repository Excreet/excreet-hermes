import { Router, type IRouter } from "express";
import Stripe from "stripe";
import { z } from "zod/v4";
import { createHmac } from "crypto";
import { logger } from "../../lib/logger.js";

const PROTOCOL_PRICE_CENTS  = 2900;
const FORMULA_PRICE_CENTS   = 6500;
const FORMULA_PRODUCT_NAME  = "Excreet Signature Formula";
const FORMULA_DESCRIPTION   = "Precision cellular health supplement — minerals, enzymes, and botanicals. 30-day supply, 60 capsules.";

function getStripe(): Stripe {
  const key = process.env.STRIPE_SECRET_KEY;
  if (!key) {
    throw new Error("STRIPE_SECRET_KEY not configured");
  }
  return new Stripe(key, { apiVersion: "2026-04-22.dahlia" });
}

/* ══════════════════════════════════════════════════════════════════════════
   CHECKOUT ROUTER — protected by requireApiKey (called by WordPress)
   ══════════════════════════════════════════════════════════════════════════ */

const checkoutRouter: IRouter = Router();

const CheckoutRequestSchema = z.object({
  wp_user_id: z.string().min(1),
  member_id:  z.string().min(1),
  return_url:  z.string().url(),
});

/**
 * POST /api/hermes/ministry/stripe/create-checkout
 *
 * Creates a Stripe Checkout Session for one $29 protocol credit.
 * Called by the WordPress AJAX handler when a member initiates purchase.
 *
 * Body:    { wp_user_id, member_id, return_url }
 * Returns: { checkout_url }
 */
checkoutRouter.post("/ministry/stripe/create-checkout", async (req, res) => {
  const parsed = CheckoutRequestSchema.safeParse(req.body);

  if (!parsed.success) {
    req.log.warn({ issues: parsed.error.issues }, "Stripe checkout: invalid request");
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  const { wp_user_id, member_id, return_url } = parsed.data;

  if (!process.env.STRIPE_SECRET_KEY) {
    req.log.error("Stripe checkout: STRIPE_SECRET_KEY not set");
    res.status(503).json({
      error:   "stripe_not_configured",
      message: "Payment processing is not yet configured. Please contact support.",
    });
    return;
  }

  try {
    const stripe  = getStripe();
    const session = await stripe.checkout.sessions.create({
      mode:       "payment",
      line_items: [
        {
          price_data: {
            currency:     "usd",
            product_data: {
              name:        "Excreet Healing Protocol — One Session",
              description: "Personalized AI-powered healing protocol. Private & confidential.",
            },
            unit_amount: PROTOCOL_PRICE_CENTS,
          },
          quantity: 1,
        },
      ],
      metadata:    { wp_user_id, member_id },
      success_url: `${return_url}?protocol_purchased=1&session_id={CHECKOUT_SESSION_ID}`,
      cancel_url:  `${return_url}?protocol_cancelled=1`,
    });

    req.log.info({ member_id, session_id: session.id }, "Stripe: checkout session created");
    res.json({ checkout_url: session.url });
  } catch (err) {
    req.log.error({ err }, "Stripe: checkout session creation failed");
    res.status(502).json({
      error:   "stripe_error",
      message: "Unable to create checkout session. Please try again.",
    });
  }
});

/* ══════════════════════════════════════════════════════════════════════════
   WEBHOOK ROUTER — no API key auth (validated by Stripe signature)
   ══════════════════════════════════════════════════════════════════════════ */

const webhookRouter: IRouter = Router();

/**
 * POST /api/hermes/ministry/stripe/webhook
 *
 * Receives Stripe events. Raw body is preserved by app.ts verify callback.
 * On checkout.session.completed: grants one protocol credit in WordPress
 * by calling POST /wp-json/excreet/v1/protocol-credit with an HMAC.
 *
 * Register this URL in the Stripe Dashboard:
 *   https://core-status-check.replit.app/api/hermes/ministry/stripe/webhook
 * Event: checkout.session.completed
 */
webhookRouter.post("/ministry/stripe/webhook", async (req, res) => {
  const sig           = req.headers["stripe-signature"];
  const webhookSecret = process.env.STRIPE_WEBHOOK_SECRET;

  if (!webhookSecret) {
    logger.error("STRIPE_WEBHOOK_SECRET not configured");
    res.status(500).json({ error: "webhook_not_configured" });
    return;
  }

  let event: Stripe.Event;

  try {
    const stripe  = getStripe();
    const rawBody = (req as unknown as { rawBody?: Buffer }).rawBody;

    if (!rawBody) {
      throw new Error("Raw body not available — ensure express.json verify callback is set");
    }

    event = stripe.webhooks.constructEvent(rawBody, sig as string, webhookSecret);
  } catch (err) {
    logger.warn({ err }, "Stripe webhook: signature verification failed");
    res.status(400).json({ error: "invalid_signature" });
    return;
  }

  if (event.type === "checkout.session.completed") {
    const session           = event.data.object as Stripe.Checkout.Session;
    const product           = session.metadata?.product ?? "protocol";
    const wp_user_id        = session.metadata?.wp_user_id;
    const stripe_session_id = session.id;
    const customer_email    = session.customer_details?.email ?? "";

    if (product === "formula") {
      // Formula purchase — notify admin; Stripe already emails the member a receipt
      logger.info(
        { stripe_session_id, customer_email, amount: session.amount_total },
        "Formula purchase completed",
      );
      try {
        await notifyAdminFormulaOrder(stripe_session_id, customer_email, session.amount_total ?? 6500);
      } catch (err) {
        logger.error({ err, stripe_session_id }, "Admin formula notification failed");
      }
    } else {
      // Protocol session credit
      if (!wp_user_id) {
        logger.warn({ session_id: stripe_session_id }, "Stripe webhook: missing wp_user_id in metadata");
      } else {
        try {
          await grantWordPressCredit(wp_user_id, stripe_session_id);
          logger.info({ wp_user_id, stripe_session_id }, "Protocol credit granted via Stripe");
        } catch (err) {
          logger.error({ wp_user_id, stripe_session_id, err }, "WordPress credit grant failed");
        }
      }
    }
  }

  res.json({ received: true });
});

/* ══════════════════════════════════════════════════════════════════════════
   FORMULA CHECKOUT — POST /api/hermes/formula/checkout
   Public endpoint — called directly from the WordPress product page JS.
   No API key required (the member is the one initiating purchase).
   ══════════════════════════════════════════════════════════════════════════ */

const formulaRouter: IRouter = Router();

const FormulaCheckoutSchema = z.object({
  wp_user_id:   z.string().min(1).optional(),
  member_email: z.string().email().optional(),
  return_url:   z.string().url(),
});

/**
 * POST /api/hermes/formula/checkout
 *
 * Creates a Stripe Checkout Session for one Excreet Signature Formula ($65).
 * Stripe sends an automatic receipt email to the customer after payment.
 * On completion, Stripe fires checkout.session.completed — handled in webhook.
 *
 * Body:    { wp_user_id?, member_email?, return_url }
 * Returns: { checkout_url }
 */
formulaRouter.post("/formula/checkout", async (req, res) => {
  const parsed = FormulaCheckoutSchema.safeParse(req.body);

  if (!parsed.success) {
    req.log.warn({ issues: parsed.error.issues }, "Formula checkout: invalid request");
    res.status(400).json({ error: "validation_error", issues: parsed.error.issues });
    return;
  }

  const { wp_user_id, member_email, return_url } = parsed.data;

  if (!process.env.STRIPE_SECRET_KEY) {
    req.log.error("Formula checkout: STRIPE_SECRET_KEY not set");
    res.status(503).json({ error: "stripe_not_configured" });
    return;
  }

  try {
    const stripe  = getStripe();
    const imgUrl  = "https://excreet.com/wp-content/uploads/2026/05/excreet-formula-bottle-237x300.png";

    const sessionParams: Stripe.Checkout.SessionCreateParams = {
      mode:       "payment",
      line_items: [
        {
          price_data: {
            currency:     "usd",
            product_data: {
              name:        FORMULA_PRODUCT_NAME,
              description: FORMULA_DESCRIPTION,
              images:      [imgUrl],
            },
            unit_amount: FORMULA_PRICE_CENTS,
          },
          quantity: 1,
        },
      ],
      metadata:         { product: "formula", wp_user_id: wp_user_id ?? "" },
      success_url:      `${return_url}?formula_purchased=1&session_id={CHECKOUT_SESSION_ID}`,
      cancel_url:       `${return_url}?formula_cancelled=1`,
      phone_number_collection: { enabled: false },
      shipping_address_collection: {
        allowed_countries: ["US", "CA", "GB", "AU"],
      },
    };

    if (member_email) {
      sessionParams.customer_email = member_email;
    }

    const session = await stripe.checkout.sessions.create(sessionParams);

    req.log.info({ wp_user_id, session_id: session.id }, "Stripe: Formula checkout session created");
    res.json({ checkout_url: session.url });
  } catch (err) {
    req.log.error({ err }, "Stripe: Formula checkout session creation failed");
    res.status(502).json({ error: "stripe_error", message: "Unable to create checkout. Please try again." });
  }
});

/* ── WordPress credit grant helper ─────────────────────────────────────── */

async function grantWordPressCredit(
  wp_user_id: string,
  stripe_session_id: string,
): Promise<void> {
  const hermesKey = process.env.HERMES_API_KEY;
  const wpUrl     = process.env.WORDPRESS_URL ?? "https://excreet.com";

  if (!hermesKey) {
    throw new Error("HERMES_API_KEY not configured — cannot sign credit grant");
  }

  const payload = `${wp_user_id}:${stripe_session_id}`;
  const hmac    = createHmac("sha256", hermesKey).update(payload).digest("hex");

  const response = await fetch(`${wpUrl}/wp-json/excreet/v1/protocol-credit`, {
    method:  "POST",
    headers: { "Content-Type": "application/json" },
    body:    JSON.stringify({ wp_user_id, stripe_session_id, hmac }),
  });

  if (!response.ok) {
    const text = await response.text().catch(() => "(unreadable)");
    throw new Error(`WordPress returned ${response.status}: ${text.slice(0, 200)}`);
  }
}

/* ── Admin formula order notification ──────────────────────────────────── */

async function notifyAdminFormulaOrder(
  stripe_session_id: string,
  customer_email:    string,
  amount_cents:      number,
): Promise<void> {
  const wpUrl      = process.env.WORDPRESS_URL ?? "https://excreet.com";
  const hermesKey  = process.env.HERMES_API_KEY ?? "";
  const adminEmail = process.env.ADMIN_EMAIL    ?? "daytoheal@yahoo.com";
  const amount     = `$${(amount_cents / 100).toFixed(2)}`;

  // Fire-and-forget POST to WordPress to trigger a WP mail notification
  const payload = JSON.stringify({ stripe_session_id, customer_email, amount, admin_email: adminEmail });
  const hmac    = createHmac("sha256", hermesKey).update(payload).digest("hex");

  await fetch(`${wpUrl}/wp-json/excreet/v1/formula-order`, {
    method:  "POST",
    headers: { "Content-Type": "application/json", "X-Excreet-HMAC": hmac },
    body:    payload,
  }).catch((err) => {
    // Non-fatal — Stripe dashboard is the source of truth for orders
    logger.warn({ err }, "Formula order WP notification failed (non-fatal)");
  });

  logger.info({ stripe_session_id, customer_email, amount }, "Formula order notification sent");
}

export { checkoutRouter as stripeCheckoutRouter, webhookRouter as stripeWebhookRouter, formulaRouter };


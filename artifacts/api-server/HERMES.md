# Hermes — Phase 5 (Production)

Backend agent layer for Excreet.com.
Sits between WordPress (SiteGround) and OpenAI.

---

## Current Status

- ✅ Phase 3 — Scaffold (auth, intake endpoint, PostgreSQL job store)
- ✅ Phase 4 — AI pipeline (WordPress → Hermes → Anthropic → result displayed on /intake-processing/)
- ✅ Phase 5 — Production deployment (Replit VM, stable `.replit.app` domain)
- ✅ Phase 6 — Ministry of Healing ($15/$25/month two-tier AI chat at /ask-the-healer/)
- ✅ Phase 7 — $29 Healing Protocol (intake snapshot → full personalized protocol via POST /api/hermes/ministry/protocol)
- ✅ Phase 8 — Protocol history (PostgreSQL-backed; every generated protocol saved to `member_protocols` table; GET /api/hermes/ministry/protocol/history/:memberId; 14-day gut score trend chart on HCC page 257)

---

## Endpoints

All endpoints are prefixed with `/api/hermes`.

### GET /api/hermes/health
**Auth required:** No

```json
{
  "service": "hermes",
  "status": "ok",
  "version": "0.1.0",
  "uptimeSeconds": 42,
  "timestamp": "2026-05-09T04:10:36.348Z"
}
```

### POST /api/hermes/intake
**Auth required:** Yes — `Authorization: Bearer <HERMES_API_KEY>`

WordPress submits member form data here via Forminator webhook.
Validates, creates a PostgreSQL job, fires the OpenAI pipeline async,
returns a `jobId` immediately (202 Accepted).

**Response (202):**
```json
{
  "jobId": "uuid",
  "status": "pending",
  "message": "Intake accepted. Poll /api/hermes/result/:jobId for your result."
}
```

### POST /api/hermes/ministry/chat
**Auth required:** Yes — `Authorization: Bearer <HERMES_API_KEY>`

One-on-one AI health session for Ministry of Healing members ($15/$25/month).
Synchronous — returns AI response directly.

**Body:**
```json
{ "member_id": "string", "message": "string", "conversation_history": [] }
```
**Response (200):**
```json
{ "response": "string", "member_id": "string" }
```

---

### POST /api/hermes/ministry/protocol
**Auth required:** Yes — `Authorization: Bearer <HERMES_API_KEY>`

Generates a complete personalized Healing Protocol by combining the member's
baseline intake history (7 fields stored from intake form) with their current
presenting concern. Powered by Claude Opus. Synchronous — takes 30–60 seconds.

**Body:**
```json
{
  "member_id": "string",
  "current_concern": "string (max 3000 chars)",
  "intake_data": {
    "age": "44", "sex": "Female",
    "symptoms": "Fatigue, Brain fog",
    "medications": "Levothyroxine 50mcg",
    "concerns": "Low energy", "surgeries": "Appendectomy 2018",
    "alias": "Sage"
  }
}
```
**Response (200):**
```json
{
  "protocol": {
    "title": "Thyroid-Gut Axis Restoration Protocol",
    "vitality_read": "...",
    "root_pattern": "...",
    "healing_approach": ["..."],
    "dietary_protocol": ["..."],
    "supplement_stack": ["..."],
    "lifestyle_shifts": ["..."],
    "labs_to_request": ["..."],
    "red_flags": ["..."],
    "follow_up": "...",
    "disclaimer": "..."
  },
  "member_id": "string",
  "generated_at": "ISO8601"
}
```

**WordPress side:** Intake snapshot stored in `_excreet_member_intake` user meta
by `excreet-hermes-patch-294.php` when intake webhook fires. Protocol credits
tracked in `_excreet_protocol_credits` user meta. $29 MemberPress one-time
product created on first load. UI rendered via `[excreet_healing_protocol]`
shortcode on page 231 (/ask-the-healer/).

---

### GET /api/hermes/result/:jobId
**Auth required:** No (polled directly from member's browser)

```json
{
  "status": "pending | processing | completed | failed",
  "result": {
    "summary": "...",
    "signals":     ["..."],
    "cautions":    ["..."],
    "suggestions": ["..."]
  }
}
```

---

## Architecture

```
Member browser
    │
    ├─► Forminator form (WordPress / SiteGround)
    │       │  on submit: webhook via Forminator addon
    │       ▼
    │   POST /wp-json/excreet/v1/intake   ← main plugin + patch
    │       │  calls Hermes synchronously
    │       ▼
    │   POST /api/hermes/intake           ← Hermes (Replit VM)
    │       │  202 Accepted + jobId
    │       │  background: OpenAI pipeline
    │       ▼
    │   PostgreSQL (Replit DB)
    │       │  result stored on job row
    │       │
    ├─► Redirect to /intake-processing/  ← patch 2.7.2-b
    │       │
    │       ▼
    │   Processing page JS polls
    │   GET /api/hermes/result/:jobId     ← Hermes result endpoint (public)
    │       │
    │       ▼
    │   Renders: Summary · Signals · Cautions · Suggestions
```

---

## WordPress Integration

The WordPress side is handled by two mu-plugins on SiteGround:
- `excreet-hermes-client.php` — main plugin (OPcache v2.7.0)
- `excreet-hermes-patch-272.php` — active patch (v2.7.2-b)

### Switching to the Production Hermes URL

After publishing, add this line to WordPress `wp-config.php` **before** `require_once ABSPATH . 'wp-settings.php'`:

```php
define( 'EXCREET_HERMES_URL', 'https://<your-replit-app>.replit.app/api/hermes/intake' );
```

WordPress evaluates `wp-config.php` before mu-plugins load, so this
`define()` wins over the constant in the main plugin. The patch and
all webhook calls will use this URL automatically.

Also add to `wp-config.php`:
```php
define( 'EXCREET_HERMES_BASE_URL', 'https://<your-replit-app>.replit.app' );
```

The `EXCREET_HERMES_BASE_URL` constant drives the result polling URL on
the processing page. Update both constants when the domain changes.

---

## Production Secrets (Replit)

| Secret | Purpose |
|--------|---------|
| `HERMES_API_KEY` | Bearer token WordPress sends with every intake request |
| `DATABASE_URL` | PostgreSQL connection string (Replit-managed DB) |
| `AI_INTEGRATIONS_ANTHROPIC_API_KEY` | Replit-managed Anthropic proxy key (auto-provisioned) |
| `AI_INTEGRATIONS_ANTHROPIC_BASE_URL` | Replit-managed Anthropic proxy URL (auto-provisioned) |
| `SESSION_SECRET` | Express session signing |

---

## Build

```bash
pnpm --filter @workspace/api-server run build
# Output: artifacts/api-server/dist/index.mjs (2.4 MB bundled)
```

Production run command (set in artifact.toml):
```
node --enable-source-maps artifacts/api-server/dist/index.mjs
```

---

## Key Files

| File | Purpose |
|------|---------|
| `src/routes/hermes/intake.ts` | Accepts form submission, creates job, fires pipeline |
| `src/routes/hermes/result.ts` | Returns job status + AI result (polled by browser) |
| `src/services/pipeline.ts` | Orchestrates pending → processing → completed |
| `src/services/ai/workflowRouter.ts` | Routes by workflow_type to specific AI handler |
| `src/lib/jobStore.ts` | PostgreSQL-backed job CRUD (Drizzle ORM) |
| `src/middlewares/auth.ts` | Bearer token validation (HERMES_API_KEY) |
| `wordpress/excreet-hermes-patch-272.php` | Intake result display patch |
| `wordpress/excreet-hermes-patch-293.php` | Ministry of Healing two-tier chat ($15/$25/month) |
| `wordpress/excreet-hermes-patch-294.php` | $29 Healing Protocol — intake snapshot + protocol UI |
| `src/routes/hermes/ministry.ts` | Ministry chat route |
| `src/routes/hermes/ministryProtocol.ts` | Protocol generation + history route (Claude Opus) |
| `src/lib/protocolStore.ts` | PostgreSQL protocol persistence (saveProtocol, getProtocolHistory) |
| `lib/db/src/schema/memberProtocols.ts` | `member_protocols` table schema |

---

## What's Next (Phase 9)

- Admin dashboard: view all member jobs, protocol requests, and statuses across all members
- Rate limiting on `/intake` (one submission per member per 24h)
- Gut snapshot streak counter (consecutive-day habit tracker)
- Support multiple intake types (follow-up, specialist referral)

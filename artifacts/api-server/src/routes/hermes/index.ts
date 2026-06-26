import { Router, type IRouter } from "express";
import { requireApiKey } from "../../middlewares/auth.js";
import healthRouter from "./health.js";
import intakeRouter from "./intake.js";
import jobStatusRouter from "./jobStatus.js";
import ministryRouter from "./ministry.js";
import ministryProtocolRouter from "./ministryProtocol.js";
import resultRouter from "./result.js";
import bodySnapshotRouter from "./bodySnapshotRoute.js";
import { stripeCheckoutRouter, stripeWebhookRouter, formulaRouter } from "./stripeRoutes.js";
import adminRouter from "./admin.js";
import affiliateRouter from "./affiliate.js";
import uploadRouter from "./upload.js";
import clinicalReportRouter from "./clinicalReport.js";
import smsRouter from "./sms.js";
import thinkTankRouter from "./thinkTank.js";
import emcRouter from "./emc.js";
import tmcRouter from "./tmc.js";
import nmcRouter from "./nmc.js";
import testimonialUploadRouter from "./testimonialUpload.js";

const router: IRouter = Router();

/**
 * Hermes route group — mounted at /api/hermes
 *
 * Public (no auth):
 *   GET  /api/hermes/health
 *   GET  /api/hermes/result/:jobId
 *   POST /api/hermes/intake/upload   (file upload from intake form)
 *   POST /api/hermes/ministry/stripe/webhook  (validated by Stripe signature)
 *
 * Protected (requires Authorization: Bearer <HERMES_API_KEY>):
 *   POST /api/hermes/intake
 *   GET  /api/hermes/job-status/:jobId
 *   POST /api/hermes/ministry/chat
 *   POST /api/hermes/ministry/protocol
 *   POST /api/hermes/ministry/stripe/create-checkout
 */

router.use(healthRouter);
router.use(resultRouter);
router.use(stripeWebhookRouter);
router.use(formulaRouter);
router.use(uploadRouter);
router.use(emcRouter);
router.use(tmcRouter);
router.use(nmcRouter);
router.use(testimonialUploadRouter);

router.use(requireApiKey);

router.use(intakeRouter);
router.use(jobStatusRouter);
router.use(ministryRouter);
router.use(ministryProtocolRouter);
router.use(bodySnapshotRouter);
router.use(stripeCheckoutRouter);
router.use(adminRouter);
router.use(affiliateRouter);
router.use(clinicalReportRouter);
router.use(smsRouter);
router.use(thinkTankRouter);

export default router;

import { Router, type IRouter } from "express";
import healthRouter from "./health";
import hermesRouter from "./hermes/index.js";
import videoRouter from "./video.js";
import videoRenderRouter from "./videoRender.js";

const router: IRouter = Router();

router.use(healthRouter);
router.use(videoRouter);
router.use(videoRenderRouter);

router.use("/hermes", hermesRouter);

export default router;

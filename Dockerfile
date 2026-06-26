FROM node:24-alpine AS builder

RUN corepack enable && corepack prepare pnpm@10.11.0 --activate

WORKDIR /app

COPY package.json pnpm-workspace.yaml pnpm-lock.yaml tsconfig.json tsconfig.base.json ./

COPY lib/ ./lib/
COPY scripts/ ./scripts/
COPY artifacts/api-server/ ./artifacts/api-server/

RUN pnpm install --frozen-lockfile --ignore-scripts

RUN pnpm run typecheck:libs

RUN pnpm --filter @workspace/api-server run build

FROM node:24-alpine AS runner

RUN corepack enable && corepack prepare pnpm@10.11.0 --activate

WORKDIR /app

COPY package.json pnpm-workspace.yaml pnpm-lock.yaml tsconfig.json tsconfig.base.json ./
COPY lib/ ./lib/
COPY scripts/ ./scripts/
COPY artifacts/api-server/package.json ./artifacts/api-server/package.json

RUN pnpm install --frozen-lockfile --ignore-scripts --prod

COPY --from=builder /app/artifacts/api-server/dist ./artifacts/api-server/dist

WORKDIR /app/artifacts/api-server

ENV NODE_ENV=production

CMD ["node", "--enable-source-maps", "./dist/index.mjs"]

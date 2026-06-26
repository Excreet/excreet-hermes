FROM node:24-alpine

RUN npm install -g pnpm@10.26.1

WORKDIR /app

COPY package.json pnpm-workspace.yaml pnpm-lock.yaml tsconfig.json tsconfig.base.json ./
COPY lib/ ./lib/
COPY scripts/ ./scripts/
COPY artifacts/api-server/ ./artifacts/api-server/

RUN pnpm install --no-frozen-lockfile --ignore-scripts

RUN pnpm --filter @workspace/api-server run build

WORKDIR /app/artifacts/api-server

ENV NODE_ENV=production

CMD ["node", "--enable-source-maps", "./dist/index.mjs"]

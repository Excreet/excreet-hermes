FROM node:24-alpine
WORKDIR /app
COPY package.json ./
RUN npm install --production
COPY dist/ ./dist/
ENV NODE_ENV=production
CMD ["node", "--enable-source-maps", "./dist/index.mjs"]

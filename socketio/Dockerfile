FROM node:22-alpine
WORKDIR /app
ENV NODE_ENV=production
COPY socketio/package.json socketio/package-lock.json ./
RUN npm ci --omit=dev --ignore-scripts
COPY socketio/socket.io.config.cjs socketio/auth.cjs socketio/env.cjs socketio/app.js ./
USER node
EXPOSE 6001
CMD ["node", "socket.io.config.cjs"]

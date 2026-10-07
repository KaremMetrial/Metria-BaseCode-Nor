FROM node:22-alpine
WORKDIR /app
ENV NODE_ENV=production
COPY docker/socketio/package.json docker/socketio/package-lock.json ./
RUN npm ci --omit=dev --ignore-scripts
COPY docker/socketio/socket.io.config.cjs docker/socketio/auth.cjs docker/socketio/env.cjs docker/socketio/app.js ./
USER node
EXPOSE 6001
CMD ["node", "socket.io.config.cjs"]

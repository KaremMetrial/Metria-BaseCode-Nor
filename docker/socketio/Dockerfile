FROM node:20-alpine
WORKDIR /app
ENV NODE_ENV=production
COPY docker/socketio/package.json docker/socketio/package-lock.json ./
RUN npm ci --omit=dev --ignore-scripts
COPY docker/socketio/socket.io.config.cjs docker/socketio/auth.cjs ./
USER node
EXPOSE 6001
CMD ["node", "socket.io.config.cjs"]

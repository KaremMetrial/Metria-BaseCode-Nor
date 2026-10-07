# === Docker Socket.IO ===
FROM node:20-alpine

# Install global deps
RUN npm install -g npm@11

# Create app directory
WORKDIR /var/www/html

# Set environment variables (can be overridden at runtime)
ENV NODE_ENV=development
ENV REDIS_HOST=redis
ENV REDIS_PORT=6379
ENV SOCKET_IO_PORT=6001
ENV CORS_ORIGIN=http://localhost:3000,http://localhost

# Copy Socket.IO config to working directory
COPY docker/socketio/socket.io.config.cjs ./socket.io.config.cjs

# Copy package.json to install dependencies
COPY docker/socketio/package.json ./package.json

# Install Socket.IO server dependencies
RUN npm install

# Expose Socket.IO port
EXPOSE 6001

# Run the Socket.IO server directly with Node.js
CMD ["node", "./socket.io.config.cjs"]

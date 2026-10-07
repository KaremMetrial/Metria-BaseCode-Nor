# === Docker Nginx ===
FROM nginx:alpine

# Install curl for health checks
RUN apk add --no-cache curl

# Copy Nginx config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Default command
CMD ["nginx", "-g", "daemon off;"]

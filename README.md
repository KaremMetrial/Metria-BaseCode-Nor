# Metrial Basecode

A Laravel + Docker starting point with a Socket.IO realtime server backed by
Redis. Everything needed to run the app — PHP, Nginx, MySQL, Redis, the queue
worker and the websocket server — is defined in `docker-compose.yml`.

## Stack

| Layer | Technology |
| --- | --- |
| Framework | Laravel 13 (PHP 8.5) |
| Web server | Nginx (alpine) |
| PHP runtime | `php:8.5-fpm-alpine`, PHP-FPM + Supervisor |
| Database | MySQL 8.0 |
| Cache / sessions / queue / pub-sub | Redis 7 |
| Realtime | Node 20 + Socket.IO 4 with `@socket.io/redis-adapter` |

## Services

| Service | Container | Host port | Notes |
| --- | --- | --- | --- |
| `web` | `metrial_web` | `80`, `443` | Nginx, serves `public/` |
| `app` | `metrial_app` | – | PHP-FPM only (Supervisor) |
| `queue` | `metrial_queue` | – | `queue:work redis` |
| `socketio` | `metrial_socketio` | `6001` | Socket.IO server + relay |
| `db` | `metrial_db` | `3308` → 3306 | MySQL |
| `redis` | `metrial_redis` | `6379` | Redis |

## Quick start

```bash
cp .env.example .env
docker compose build
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Then open <http://localhost>.

## Realtime architecture

Laravel never terminates websocket connections. It only *publishes* to Redis,
and the Socket.IO container relays those messages to connected clients:

```
browser ──(HTTP)──> Nginx ──> PHP-FPM ──(publish)──> Redis pub/sub
                                                            │
browser <──(websocket, :6001)── Socket.IO container <───────┘
```

Concretely, for `POST /api/events/test`:

1. `app/Events/MessageSent` broadcasts on channel `room.{room}`. Because it
   implements `ShouldBroadcast`, Laravel serialises
   `{event, data, socket}` and publishes it to the Redis channel
   `REDIS_PREFIX + 'room.' + room` — e.g. `metrial_room.smoke`.
2. `docker/socketio/socket.io.config.cjs` is subscribed to `REDIS_PREFIX*`. It
   strips the prefix to recover the channel name and emits the event inside the
   Socket.IO room of the same name.

> The pub/sub channel name is `database.redis.options.prefix` + the channel
> name, so **`REDIS_PREFIX` must be identical for the `app`, `queue` and
> `socketio` services**. It is passed to all three in `docker-compose.yml`.

### Broadcasting from Laravel

```php
broadcast(new App\Events\MessageSent($message, $userId, $room));
```

### Listening from the front-end

```js
import { io } from 'socket.io-client';

const socket = io('http://localhost:6001');

socket.on('connect', () => socket.emit('join:room', 'room.smoke'));
socket.on('message:sent', (payload) => console.log(payload));
```

Server-emitted events: `message:received`, `typing:started`, `typing:stopped`,
`client:connected`, `client:disconnected`. Client-emitted events: `join:room`,
`leave:room`, `message:send`, `typing:start`.

Health check: `curl http://localhost:6001/health`.

## Common commands

```bash
docker compose up -d                     # start everything
docker compose logs -f app socketio      # tail logs
docker compose exec app php artisan ...  # artisan
docker compose exec app composer ...     # composer
docker compose exec app php artisan migrate
docker compose exec app php artisan queue:work   # ad-hoc worker
docker compose exec db mysql -umetrial -pmetrial metrial
docker compose down                      # stop (volumes are kept)
```

## Configuration notes

These are the settings most easily got wrong:

- **`DB_PORT` vs `DB_HOST_PORT`.** `DB_PORT=3306` is the port *inside* the
  Docker network. MySQL is published to the host on `DB_HOST_PORT=3308`.
- **`SESSION_CONNECTION`.** This is a Redis *connection name* from
  `config/database.php` (`default` or `cache`), not the session driver. The
  driver is `SESSION_DRIVER`.
- **`REDIS_CLIENT=predis`.** The stack uses the `predis` client rather than the
  `phpredis` extension. `BROADCAST_CONNECTION` and `QUEUE_CONNECTION` are
  driver names and should stay `redis`.
- **`REDIS_PREFIX=metrial_`.** Keep in sync with the `socketio` service.
- **PHP-FPM and file permissions.** FPM runs as the host user's UID/GID
  (`WWWUSER`/`WWWGROUP`, default `1000`) so the bind-mounted `storage/` and
  `bootstrap/cache/` directories are writable from both the host and the
  container. If your host UID differs, set it when building:

  ```bash
  WWWUSER=$(id -u) WWWGROUP=$(id -g) docker compose build app queue
  ```

- **Nginx upstream resolution.** `docker/nginx/default.conf` resolves the `app`
  container through Docker's embedded DNS (`127.0.0.11`) at request time, so
  recreating the `app` container does not require restarting `web`.
- **Config bind mounts.** `docker/php/zz-docker.conf` and
  `docker/nginx/default.conf` are bind-mounted read-only, so the files in this
  repository are always the ones in effect — no rebuild needed to change them.

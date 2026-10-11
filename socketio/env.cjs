const path = require('node:path');

// Resolve beside the application even when Passenger starts from another cwd.
require('dotenv').config({
  path: process.env.DOTENV_CONFIG_PATH || path.join(__dirname, '.env'),
  override: false,
  quiet: true,
});

// Laravel's unquoted null sentinel can arrive as a literal string via Compose.
if (['null', '(null)'].includes((process.env.REDIS_PASSWORD || '').toLowerCase())) {
  delete process.env.REDIS_PASSWORD;
}

const path = require('node:path');

// Resolve beside the application even when Passenger starts from another cwd.
require('dotenv').config({
  path: process.env.DOTENV_CONFIG_PATH || path.join(__dirname, '.env'),
  override: false,
  quiet: true,
});

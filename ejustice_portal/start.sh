#!/usr/bin/env sh
# Simple start script for Railway / Railpack
# Starts the built-in PHP server and serves the `public/` directory.
# Uses $PORT if available (Railway sets it), otherwise defaults to 8080.

PORT=${PORT:-8080}

# Ensure public directory exists
if [ ! -d "public" ]; then
  echo "Error: 'public' directory not found. Are you in the project root?"
  exit 1
fi

echo "Starting PHP built-in server on 0.0.0.0:$PORT (serving public/)..."
exec php -S 0.0.0.0:$PORT -t public

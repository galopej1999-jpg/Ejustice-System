<?php
// General app configuration
// Reads from environment variables (set by Railway, Docker, or .env)
// Falls back to defaults for local development

// Database configuration
// Railway injects: DATABASE_URL or individual DB vars
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'ejustice_portal');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// Encryption
define('DOC_ENC_METHOD', 'AES-256-CBC');
// Generate a secure key on production: openssl rand -hex 32
// Set via environment variable DOC_ENC_KEY
define('DOC_ENC_KEY', getenv('DOC_ENC_KEY') ?: hash('sha256', 'CHANGE_ME_SUPER_SECRET_KEY_PROD'));

// Application environment
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', getenv('APP_DEBUG') ?: false);

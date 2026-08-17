<?php

/*
|--------------------------------------------------------------------------
| F01 — Configuration contract
|--------------------------------------------------------------------------
|
| .env.example is the promise that a straight copy to .env boots the stack.
| These tests make that promise checkable: a config key the application reads
| but the example file omits is a defect, not a runtime surprise.
|
*/

/**
 * Env keys referenced by config/ that belong to services this application never
 * activates. Shipping credentials for S3, Pusher, or Postmark in .env.example
 * would be noise, so they are excluded from the completeness check by prefix.
 */
const INACTIVE_SERVICE_PREFIXES = [
    'ABLY_',        // broadcasting driver: not used (reverb)
    'AWS_',         // filesystem/queue driver: not used (local, redis)
    'DYNAMODB_',    // cache driver: not used (redis)
    'MEMCACHED_',   // cache driver: not used (redis)
    'BEANSTALKD_',  // queue driver: not used (redis)
    'SQS_',         // queue driver: not used (redis)
    'PUSHER_',      // broadcasting driver: not used (reverb)
    'POSTMARK_',    // mail transport: not used (log)
    'RESEND_',      // mail transport: not used (log)
    'SLACK_',           // notification/log channel: not used
    'PAPERTRAIL_',      // log channel: not used
    'LOG_SLACK_',       // log channel: not used
    'LOG_PAPERTRAIL_',  // log channel: not used
];

/**
 * Individual keys that carry a working default in config/ and are deliberately
 * not surfaced, so the example file stays readable.
 */
const OPTIONAL_KEYS = [
    'APP_PREVIOUS_KEYS',            // only needed while rotating APP_KEY
    'APP_MAINTENANCE_STORE',
    'AUTH_GUARD',
    'AUTH_MODEL',
    'AUTH_PASSWORD_BROKER',
    'AUTH_PASSWORD_RESET_TOKEN_TABLE',
    'AUTH_PASSWORD_TIMEOUT',
    'DB_URL',
    'DB_SOCKET',
    'DB_CHARSET',
    'DB_COLLATION',
    'DB_ENCRYPT',
    'DB_SSLMODE',
    'DB_FOREIGN_KEYS',
    'DB_TRUST_SERVER_CERTIFICATE',
    'MYSQL_ATTR_SSL_CA',
    'DB_CACHE_TABLE',
    'DB_CACHE_CONNECTION',
    'DB_CACHE_LOCK_TABLE',
    'DB_CACHE_LOCK_CONNECTION',
    'DB_QUEUE',
    'DB_QUEUE_TABLE',
    'DB_QUEUE_CONNECTION',
    'DB_QUEUE_RETRY_AFTER',
    'QUEUE_FAILED_DRIVER',
    'REDIS_URL',
    'REDIS_USERNAME',
    'REDIS_TIMEOUT',
    'REDIS_DB',
    'REDIS_CACHE_DB',
    'REDIS_PREFIX',
    'REDIS_CLUSTER',
    'REDIS_PERSISTENT',
    'REDIS_QUEUE',
    'REDIS_QUEUE_CONNECTION',
    'REDIS_QUEUE_RETRY_AFTER',
    'REDIS_CACHE_CONNECTION',
    'REDIS_CACHE_LOCK_CONNECTION',
    'REDIS_MAX_RETRIES',
    'REDIS_BACKOFF_ALGORITHM',
    'REDIS_BACKOFF_BASE',
    'REDIS_BACKOFF_CAP',
    'SESSION_CONNECTION',
    'SESSION_COOKIE',
    'SESSION_STORE',
    'SESSION_TABLE',
    'SESSION_SAME_SITE',
    'SESSION_HTTP_ONLY',
    'SESSION_SECURE_COOKIE',
    'SESSION_EXPIRE_ON_CLOSE',
    'SESSION_PARTITIONED_COOKIE',
    'LOG_DAILY_DAYS',
    'LOG_SYSLOG_FACILITY',
    'LOG_STDERR_FORMATTER',
    'LOG_DEPRECATIONS_TRACE',
    'MAIL_URL',
    'MAIL_HOST',
    'MAIL_PORT',
    'MAIL_SCHEME',
    'MAIL_USERNAME',
    'MAIL_PASSWORD',
    'MAIL_EHLO_DOMAIN',
    'MAIL_FROM_NAME',
    'MAIL_FROM_ADDRESS',
    'MAIL_LOG_CHANNEL',
    'MAIL_SENDMAIL_PATH',
    'HORIZON_PATH',
    'HORIZON_NAME',
    'HORIZON_DOMAIN',
    'REVERB_SERVER',
    'REVERB_SERVER_PATH',
    'REVERB_APPS',
    'REVERB_SCALING_ENABLED',
    'REVERB_SCALING_CHANNEL',
    'REVERB_MAX_REQUEST_SIZE',
    'REVERB_ALLOWED_ORIGINS',
    'REVERB_APP_PING_INTERVAL',
    'REVERB_APP_ACTIVITY_TIMEOUT',
    'REVERB_APP_MAX_MESSAGE_SIZE',
    'REVERB_HEALTH_CHECK_INTERVAL',
    'REVERB_APP_MAX_CONNECTIONS',
    'REVERB_APP_MAX_BACKUP_COUNT',
    'REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM',
    'REVERB_APP_RATE_LIMITING_ENABLED',
    'REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS',
    'REVERB_APP_RATE_LIMIT_DECAY_SECONDS',
    'REVERB_APP_RATE_LIMIT_TERMINATE',
    'REVERB_PULSE_INGEST_INTERVAL',
    'REVERB_TELESCOPE_INGEST_INTERVAL',
];

/**
 * @return list<string> every key referenced through env() anywhere in config/
 */
function configuredEnvKeys(): array
{
    $keys = [];

    foreach (glob(base_path('config/*.php')) as $file) {
        preg_match_all('/env\(\s*[\'"]([A-Z0-9_]+)[\'"]/', file_get_contents($file), $matches);
        $keys = array_merge($keys, $matches[1]);
    }

    return array_values(array_unique($keys));
}

/**
 * @return array<string, string> the key/value pairs declared in .env.example
 */
function exampleEnv(): array
{
    $pairs = [];

    foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $pairs[trim($key)] = trim(trim($value), '"\'');
    }

    return $pairs;
}

test('the .env.example contains every key the application reads', function () {
    $shipped = array_keys(exampleEnv());

    $required = array_filter(configuredEnvKeys(), function (string $key) {
        if (in_array($key, OPTIONAL_KEYS, true)) {
            return false;
        }

        foreach (INACTIVE_SERVICE_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return false;
            }
        }

        return true;
    });

    $missing = array_values(array_diff($required, $shipped));

    expect($missing)->toBe([], 'chaves ausentes no .env.example: '.implode(', ', $missing));
});

test('the .env.example delivers every key in the documented contract', function () {
    // Section 5 of the F01 spec — the groups the README promises.
    $contract = [
        'APP_NAME', 'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL', 'APP_LOCALE',
        'APP_FALLBACK_LOCALE', 'APP_FAKER_LOCALE', 'APP_TIMEZONE', 'APP_PORT',
        'LOG_CHANNEL', 'LOG_STACK', 'LOG_LEVEL', 'LOG_DEPRECATIONS_CHANNEL',
        'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME',
        'DB_PASSWORD', 'DB_ROOT_PASSWORD', 'FORWARD_DB_PORT',
        'SESSION_DRIVER', 'SESSION_LIFETIME', 'SESSION_ENCRYPT', 'SESSION_PATH', 'SESSION_DOMAIN',
        'CACHE_STORE', 'CACHE_PREFIX', 'QUEUE_CONNECTION',
        'REDIS_CLIENT', 'REDIS_HOST', 'REDIS_PASSWORD', 'REDIS_PORT', 'FORWARD_REDIS_PORT',
        'BROADCAST_CONNECTION',
        'REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET', 'REVERB_HOST',
        'REVERB_PORT', 'REVERB_SCHEME', 'REVERB_SERVER_HOST', 'REVERB_SERVER_PORT',
        'VITE_APP_NAME', 'VITE_REVERB_APP_KEY', 'VITE_REVERB_HOST',
        'VITE_REVERB_PORT', 'VITE_REVERB_SCHEME',
        'HORIZON_PREFIX',
        'FILESYSTEM_DISK', 'MAIL_MAILER',
    ];

    $missing = array_values(array_diff($contract, array_keys(exampleEnv())));

    expect($missing)->toBe([], 'chaves do contrato ausentes: '.implode(', ', $missing));
});

test('the .env.example contains no placeholders that require manual editing', function () {
    $placeholders = [];
    $empty = [];

    foreach (exampleEnv() as $key => $value) {
        if ($value === '') {
            $empty[] = $key;

            continue;
        }

        if (preg_match('/(your[-_]|changeme|<[^>]+>|xxxx|TODO)/i', $value)) {
            $placeholders[] = $key;
        }
    }

    expect($placeholders)->toBe([], 'valores que exigem edição manual: '.implode(', ', $placeholders));

    // APP_KEY is the single intentional blank — the entrypoint generates it.
    expect($empty)->toBe(['APP_KEY']);
});

test('the cache, queue, and broadcast connections point to the compose services', function () {
    // Read the contract file rather than the resolved config: phpunit.xml
    // deliberately swaps these drivers out for array/sync while testing.
    $env = exampleEnv();

    expect($env['CACHE_STORE'])->toBe('redis')
        ->and($env['QUEUE_CONNECTION'])->toBe('redis')
        ->and($env['BROADCAST_CONNECTION'])->toBe('reverb')
        ->and($env['SESSION_DRIVER'])->toBe('database')
        ->and($env['REDIS_CLIENT'])->toBe('phpredis')
        ->and($env['DB_CONNECTION'])->toBe('mysql')
        // Service names on the compose network, not localhost.
        ->and($env['DB_HOST'])->toBe('mysql')
        ->and($env['REDIS_HOST'])->toBe('redis')
        // The Reverb server has to bind every interface inside its container.
        ->and($env['REVERB_SERVER_HOST'])->toBe('0.0.0.0');
});

test('the application locale is pt_BR', function () {
    expect(config('app.locale'))->toBe('pt_BR')
        ->and(config('app.fallback_locale'))->toBe('pt_BR')
        ->and(config('app.timezone'))->toBe('America/Sao_Paulo');
});

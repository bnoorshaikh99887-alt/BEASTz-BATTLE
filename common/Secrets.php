<?php
/**
 * BEASTz BATTLE - Centralized Secrets & Environment Config Manager
 * Loads and validates secrets from .env, system env, and server-side config.
 * Supports typed access, required validation, and credential bundling for TiDB, Cashfree, & OneSignal.
 */

if (!defined('BEASTZ_SECRETS_LOADED')) {
    define('BEASTZ_SECRETS_LOADED', true);

    if (file_exists(__DIR__ . '/env.php')) {
        require_once __DIR__ . '/env.php';
    }

    class Secrets {
        /**
         * Cached key-value pairs
         * @var array<string, mixed>
         */
        protected static array $cache = [];

        /**
         * Retrieve a secret by key with an optional default.
         *
         * @param string $key
         * @param mixed $default
         * @return mixed
         */
        public static function get(string $key, $default = null) {
            if (array_key_exists($key, self::$cache)) {
                return self::$cache[$key] ?? $default;
            }

            // Check env() helper function first
            if (function_exists('env')) {
                $val = env($key, null);
                if ($val !== null && $val !== '') {
                    self::$cache[$key] = $val;
                    return $val;
                }
            }

            // Fallback to getenv, $_ENV, $_SERVER
            $val = getenv($key);
            if ($val === false || $val === '') {
                $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
            }

            if ($val !== null && $val !== '') {
                self::$cache[$key] = $val;
                return $val;
            }

            return $default;
        }

        /**
         * Retrieve a required secret. Throws RuntimeException if not defined or empty.
         *
         * @param string $key
         * @return string
         * @throws RuntimeException
         */
        public static function require(string $key): string {
            $val = self::get($key, null);
            if ($val === null || $val === '') {
                throw new RuntimeException("Missing required secret configuration: '{$key}'");
            }
            return (string)$val;
        }

        /**
         * Check if a secret exists and has a non-empty value.
         *
         * @param string $key
         * @return bool
         */
        public static function has(string $key): bool {
            $val = self::get($key, null);
            return $val !== null && $val !== '';
        }

        /**
         * Retrieve a boolean secret (accepts 'true', '1', 'yes', 'on', 'enabled')
         *
         * @param string $key
         * @param bool $default
         * @return bool
         */
        public static function bool(string $key, bool $default = false): bool {
            $val = self::get($key, null);
            if ($val === null || $val === '') return $default;
            return filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }

        /**
         * Retrieve an integer secret
         *
         * @param string $key
         * @param int $default
         * @return int
         */
        public static function int(string $key, int $default = 0): int {
            $val = self::get($key, null);
            if ($val === null || $val === '') return $default;
            return (int)$val;
        }

        /**
         * Retrieve a float secret
         *
         * @param string $key
         * @param float $default
         * @return float
         */
        public static function float(string $key, float $default = 0.0): float {
            $val = self::get($key, null);
            if ($val === null || $val === '') return $default;
            return (float)$val;
        }

        /**
         * TiDB Cloud live credentials bundle
         *
         * @return array{host: string, user: string, pass: string, name: string, port: int}
         */
        public static function db(): array {
            return [
                'host' => (string)self::get('DB_HOST', 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com'),
                'user' => (string)self::get('DB_USER', '33NZV583e1gPsMh.root'),
                'pass' => (string)self::get('DB_PASS', 'mcj5pocb1FlVZD68'),
                'name' => (string)self::get('DB_NAME', 'test'),
                'port' => self::int('DB_PORT', 4000),
            ];
        }

        /**
         * Cashfree Payment Gateway credentials bundle
         *
         * @return array{app_id: string, secret_key: string, mode: string, api_url: string}
         */
        public static function cashfree(): array {
            $mode = strtoupper((string)self::get('CASHFREE_ENVIRONMENT', self::get('CASHFREE_MODE', 'PROD')));
            $apiUrl = ($mode === 'SANDBOX') 
                ? 'https://sandbox.cashfree.com/pg' 
                : 'https://api.cashfree.com/pg';

            return [
                'app_id'     => (string)self::get('CASHFREE_APP_ID', '1494190b594e98ed762a2a943320914941'),
                'secret_key' => (string)self::get('CASHFREE_SECRET_KEY', 'cfsk_ma_prod_979295dd86c34ea7eef3d56c1e07d25e_27097462'),
                'mode'       => $mode,
                'api_url'    => $apiUrl
            ];
        }

        /**
         * OneSignal Push Notification credentials bundle
         *
         * @return array{app_id: string, api_key: string}
         */
        public static function onesignal(): array {
            return [
                'app_id'  => (string)self::get('ONESIGNAL_APP_ID', '561bd59e-8355-47c8-88cc-0766688a105a'),
                'api_key' => (string)self::get('ONESIGNAL_API_KEY', self::get('ONESIGNAL_REST_KEY', base64_decode('b3NfdjJfYXBwX2t5bjVsaHVka3ZkNHJjZ21hNXRncmNxcWxrM3A0bTR0NXliZTQ1ZnpmbWJjZ3VlYWtmb2xwdHFycWlld25ybXA2N21pNHB4azV3aHllMzZiNTVzbTQ3bDdxbXF0ZGJ2MnVmZmpzYXk='))),
            ];
        }

        /**
         * Check if app is in production mode
         *
         * @return bool
         */
        public static function isProduction(): bool {
            $env = strtolower((string)self::get('APP_ENV', 'production'));
            return $env === 'production' || $env === 'prod';
        }
    }

    if (!function_exists('secret')) {
        /**
         * Procedural helper for quick secret retrieval
         */
        function secret(string $key, $default = null) {
            return Secrets::get($key, $default);
        }
    }
}

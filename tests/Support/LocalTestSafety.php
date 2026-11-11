<?php

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use PDO;
use RuntimeException;

final class LocalTestSafety
{
    private static array $settings = [];

    private static string $runtime;

    public static function bootstrap(): void
    {
        if (self::$settings !== []) {
            return;
        }

        $file = dirname(__DIR__, 2).'/.env.testing';
        if (! is_file($file) || getenv('APP_ENV') !== 'testing') {
            throw new RuntimeException('Testing requires APP_ENV=testing and an explicit local .env.testing.');
        }

        $settings = \Dotenv\Dotenv::parse(file_get_contents($file));
        if (($settings['APP_ENV'] ?? '') !== 'testing'
            || ($settings['DB_CONNECTION'] ?? '') !== 'mysql'
            || ! in_array($settings['DB_HOST'] ?? '', ['127.0.0.1', 'localhost'], true)
            || ! preg_match('/^friendie_logistics_testing_[0-9]{8}_[0-9]{6}$/D', $settings['DB_DATABASE'] ?? '')
            || ! preg_match('/^fltest_[a-f0-9]{8}$/D', $settings['DB_USERNAME'] ?? '')
            || empty($settings['DB_PASSWORD'])
            || ! empty($settings['DB_URL'])) {
            throw new RuntimeException('Unsafe test connection configuration; refusing to bootstrap.');
        }

        foreach (['DB_DATABASE', 'DB_USERNAME', 'DB_HOST', 'DB_CONNECTION'] as $key) {
            $ambient = getenv($key);
            if ($ambient !== false && $ambient !== '' && $ambient !== $settings[$key]) {
                throw new RuntimeException('Conflicting database environment override; refusing to bootstrap.');
            }
        }
        if (getenv('DB_URL')) {
            throw new RuntimeException('Database URL override is forbidden in tests.');
        }

        self::verifyPrivileges($settings);
        self::$runtime = str_replace('\\', '/', sys_get_temp_dir())
            .'/friendie-logistics-tests-'.getmypid().'-'.bin2hex(random_bytes(6));
        foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views',
            'storage/logs', 'storage/app/private', 'storage/app/public', 'cache',
            'public/frontend/img/profiles', 'public/frontend/img/listing',
            'public/backend/img/category', 'public/backend/img/flags'] as $directory) {
            if (! mkdir(self::$runtime.'/'.$directory, 0700, true) && ! is_dir(self::$runtime.'/'.$directory)) {
                throw new RuntimeException('Cannot prepare isolated test filesystem.');
            }
        }

        $settings = array_merge($settings, [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_URL' => '',
            'MAIL_MAILER' => 'array', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'null', 'QUEUE_FAILED_DRIVER' => 'null', 'LOG_CHANNEL' => 'null',
            'APP_CONFIG_CACHE' => self::$runtime.'/cache/config.php',
            'APP_SERVICES_CACHE' => self::$runtime.'/cache/services.php',
            'APP_PACKAGES_CACHE' => self::$runtime.'/cache/packages.php',
            'APP_ROUTES_CACHE' => self::$runtime.'/cache/routes.php',
            'APP_EVENTS_CACHE' => self::$runtime.'/cache/events.php',
            'VIEW_COMPILED_PATH' => self::$runtime.'/storage/framework/views',
        ]);
        foreach ($settings as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        self::$settings = $settings;
    }

    private static function verifyPrivileges(array $settings): void
    {
        try {
            $pdo = new PDO(
                'mysql:host='.$settings['DB_HOST'].';port='.$settings['DB_PORT']
                    .';dbname='.$settings['DB_DATABASE'].';charset=utf8mb4',
                $settings['DB_USERNAME'], $settings['DB_PASSWORD'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $settings['DB_DATABASE']) {
                throw new RuntimeException('Test database identity mismatch.');
            }
            foreach ($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $grant) {
                if (! str_starts_with($grant, 'GRANT USAGE ON *.*')
                    && ! str_contains($grant, ' ON `'.$settings['DB_DATABASE'].'`.* TO ')) {
                    throw new RuntimeException('Test account has privileges outside its dedicated schema.');
                }
            }
            try {
                $pdo->query('SELECT id FROM `friendie_logistics`.`users` LIMIT 0');
            } catch (\PDOException $exception) {
                if (in_array($exception->errorInfo[1] ?? null, [1044, 1142], true)) {
                    return;
                }
                throw new RuntimeException('Original-database access denial could not be verified.');
            }
            throw new RuntimeException('Test account can access the original database.');
        } catch (\PDOException) {
            throw new RuntimeException('Test database preflight failed; connection details withheld.');
        }
    }

    public static function createApplication()
    {
        self::bootstrap();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->addAbsoluteCachePathPrefix(self::$runtime.'/');
        $app->loadEnvironmentFrom('.env.testing');
        $app->useStoragePath(self::$runtime.'/storage');
        $app->usePublicPath(self::$runtime.'/public');
        $app->afterBootstrapping(LoadConfiguration::class, function ($app): void {
            $connection = $app['config']->get('database.connections.mysql');
            foreach (['database' => 'DB_DATABASE', 'username' => 'DB_USERNAME', 'host' => 'DB_HOST'] as $field => $key) {
                if (($connection[$field] ?? null) !== self::$settings[$key]) {
                    throw new RuntimeException('Loaded database configuration does not match verified test target.');
                }
            }
            $connection['url'] = null;
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections', ['mysql' => $connection]);
            $app['config']->set('cache.default', 'array');
            $app['config']->set('session.driver', 'array');
            $app['config']->set('mail.default', 'array');
            $app['config']->set('queue.default', 'null');
            $app['config']->set('queue.connections', ['null' => ['driver' => 'null']]);
            $app['config']->set('queue.failed.driver', 'null');
            $app['config']->set('logging.default', 'null');
            $app['config']->set('filesystems.default', 'local');
            $app['config']->set('filesystems.disks', [
                'local' => ['driver' => 'local', 'root' => self::$runtime.'/storage/app/private'],
                'public' => ['driver' => 'local', 'root' => self::$runtime.'/storage/app/public'],
            ]);
        });
        $app->register(SafetyServiceProvider::class);
        chdir(self::$runtime.'/public');
        $app->make(Kernel::class)->bootstrap();
        if (! $app->environment('testing')
            || $app['db']->connection()->getDatabaseName() !== self::$settings['DB_DATABASE']) {
            throw new RuntimeException('Final test application identity check failed.');
        }

        return $app;
    }
}

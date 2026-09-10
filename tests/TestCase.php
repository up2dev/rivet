<?php
/**
 * TestCase class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests;

use Rivet\Providers\LaravelServiceProvider;
use Rivet\Providers\EventServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * TestCase
 *
 * Boots a minimal real Laravel app (via Orchestra Testbench) with
 * Rivet's own providers registered - so tests exercise
 * the actual routes/middleware/migrations shipped by the package, on
 * an in-memory SQLite database, not a hand-rolled fake.
 *
 * @category Test
 * @package  Rivet\Tests
 * @license  https://opensource.org/licenses/MIT MIT License
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Register the package's own providers, exactly as a consuming app's
     * composer.json would via 'extra.laravel.providers'.
     *
     * @param \Illuminate\Foundation\Application $app The application
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            // Registered explicitly: package auto-discovery alone
            // doesn't register Sanctum's driver in this isolated
            // Testbench setup.
            \Laravel\Sanctum\SanctumServiceProvider::class,
            LaravelServiceProvider::class,
            EventServiceProvider::class,
        ];
    }

    /**
     * Force an in-memory SQLite connection for tests, regardless of
     * whatever .env a developer has locally.
     *
     * @param \Illuminate\Foundation\Application $app The application
     *
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Creating a User with no email_verified_at triggers a
        // validation email via a model event, which builds this
        // Address in its constructor even under Mail::fake().
        $app['config']->set('mail.from.address', 'test@example.test');
        $app['config']->set('mail.from.name', 'Rivet Test');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => ''
        ]);

        // Sanctum's guard needs an explicit key in testing envs.
        $app['config']->set('app.key', 'base64:'.base64_encode(
            random_bytes(32)
        ));

        // This package's own config/auth.php ships only a 'web' guard
        // - defining 'sanctum' is left to the consuming application,
        // per Laravel Sanctum's own install instructions.
        $app['config']->set('auth.guards.sanctum', [
            'driver'   => 'sanctum',
            'provider' => null
        ]);
    }

    /**
     * Migrate the package's own tables (users, tokens, roles, ...) plus
     * any test-only fixture tables (see tests/Fixtures/Migrations).
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {
        // Loaded explicitly rather than relying on the package
        // provider's own migration registration timing.
        $this->loadMigrationsFrom($this->realMigrationsPathWithoutMongo());
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/Migrations');
    }

    /**
     * Foundation's own migrations minus the 'logs' table, which lives
     * on a dedicated MongoDB connection this test environment doesn't
     * configure.
     *
     * @return string Path to a filtered copy of the migrations folder
     */
    private function realMigrationsPathWithoutMongo(): string
    {
        $source = realpath(__DIR__.'/../database/migrations');
        $target = sys_get_temp_dir().'/rivet-test-migrations';

        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }

        foreach (glob("{$source}/*.php") as $file) {
            if (str_contains($file, 'create_logs_table')) {
                continue;
            }

            copy($file, $target.'/'.basename($file));
        }

        return $target;
    }
}

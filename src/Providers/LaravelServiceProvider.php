<?php
/**
 * LaravelServiceProvider class file
 *
 * PHP Version 8.1
 *
 * @category Service
 * @package  Rivet\Providers
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Providers;

// use Rivet\Http\Middleware\DataCheck;
use Rivet\Http\Middleware\DataValidate;
use Rivet\Http\Middleware\QueryStringToConfig;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Rivet\Data\Models\Auth\AccessToken;
use Rivet\Http\Middleware\Authenticate;
use Rivet\Http\Middleware\ResolvePendingTwoFactorToken;
use Rivet\Contracts\Auth\LoginChallenger;
use Rivet\Services\TwoFactorLoginChallenger;
use Rivet\Commands\Permissions;
use Rivet\Commands\DefaultRole;
use Rivet\Commands\RightsManagement;
use Rivet\Console\Commands\MakeCrudCommand;

/**
 * LaravelServiceProvider
 *
 * @category Service
 * @package  Rivet\Providers
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Register bindings.
     *
     * @return void
     */
    public function register(): void
    {
        // Rivet's own two-factor authentication is bound here as the
        // default LoginChallenger - not a hard dependency of
        // AuthController, which only checks whether anything is
        // bound to the interface at all (see src/Contracts/Auth/
        // LoginChallenger.php). A host application can override this
        // binding with its own implementation if it needs entirely
        // different login-time behaviour.
        $this->app->bind(LoginChallenger::class, TwoFactorLoginChallenger::class);
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot(): void
    {
        // Query logging used to be enabled unconditionally, on every
        // request, in every environment, purely so ResponseService
        // could report 'sql_duration' in its metadata. Every query
        // accumulates in memory for the whole request lifecycle - a
        // real concern on any endpoint doing a lot of queries (N+1
        // included), and something Laravel Octane/Swoole explicitly
        // warns against enabling by default. It's now opt-in via
        // config('logs.sql_duration'), off by default.
        if (config('logs.sql_duration')) {
            DB::enableQueryLog();
        }

        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/app.php'), 'app'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/mail.php'), 'mail'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/auth.php'), 'auth'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/crud.php'), 'crud'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/logs.php'), 'logs'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/paginator.php'), 'paginator'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/query.php'), 'query'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/sanctum.php'), 'sanctum'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/sendmail.php'), 'sendmail'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/storage.php'), 'storage'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/permissions.php'), 'permissions'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../../config/two_factor.php'), 'two_factor'
        );

        $this->loadMigrationsFrom(
            realpath(__DIR__.'/../../database/migrations')
        );

        $this->loadTranslationsFrom(
            realpath(__DIR__.'/../../resources/lang/'), 'rivet'
        );

        $this->loadViewsFrom(
            realpath(__DIR__.'/../../resources/views/'), 'rivet'
        );

        $this->commands([
            // Permissions::class,
            // DefaultRole::class,
            RightsManagement::class,
            MakeCrudCommand::class
        ]);

        // $this->publishes([
        //     __DIR__.'/../lang' => $this->app->langPath('vendor/courier'),
        // ]);
        // $this->loadRoutesFrom(
        //     realpath(__DIR__.'/../../routes/api.php')
        // );
        Route::pushMiddlewareToGroup('api', QueryStringToConfig::class);
        Route::aliasMiddleware('dataValidation', DataValidate::class);
        Route::aliasMiddleware('lpfauth', Authenticate::class);
        Route::aliasMiddleware('resolvePendingTwoFactor', ResolvePendingTwoFactorToken::class);

        Route::middleware('api')->namespace(
            'Rivet\\Http\\Controllers'
        )->prefix('api')->group(
            realpath(__DIR__.'/../../routes/api.php')
        );

        // Pre-existing bug, surfaced by running the new test suite:
        // composer.json has no upper bound on laravel/sanctum ('>=3'),
        // so a fresh `composer install` today pulls whatever the
        // latest Sanctum is - which has removed ignoreMigrations() in
        // some versions still compatible with Laravel <12. Guarding
        // with method_exists() keeps this working across Sanctum
        // versions without having to pin one (which could have other
        // knock-on effects on Laravel 10/11/12 compatibility that
        // can't be verified without actually running composer).
        if (
            intval(Str::before(app()->version(), '.')) < 12 &&
            method_exists(Sanctum::class, 'ignoreMigrations')
        ) {
            Sanctum::ignoreMigrations();
        }

        Sanctum::usePersonalAccessTokenModel(AccessToken::class);

        config([ 'logs.process' => (
            Request::has('lproc')? Request::get('lproc'): uniqid()
        ) ]);
        // dd(config('logs.process'));
    }
}

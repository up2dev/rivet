<?php
/**
 * LoginCaseSensitivityTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Auth;

use Rivet\Tests\TestCase;
use Rivet\Data\Models\Auth\User;

/**
 * LoginCaseSensitivityTest
 *
 * The underlying bug (env() read directly in a controller, returning
 * null once `php artisan config:cache` has run in production) can't be
 * reproduced literally in a test process - config:cache is a
 * filesystem artifact of a real deployment. What we CAN assert, and
 * what actually protects against a regression back to env(), is that
 * AuthController::login() reacts to config('auth.login_case_sensitive')
 * - which is what config:cache preserves - rather than to the
 * IS_LOGIN_KS env var directly.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class LoginCaseSensitivityTest extends TestCase
{
    /**
     * Default (false): login is compared lowercased.
     *
     * @return void
     */
    public function testLoginIsCaseInsensitiveByDefault(): void
    {
        User::create([
            'login'             => 'JDoe',
            'email'             => 'jdoe@example.test',
            'email_verified_at' => now(),
            'password'          => 'Passw0rd!'
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ]);

        $response->assertStatus(200);
    }

    /**
     * With config('auth.login_case_sensitive') set to true, an
     * incorrectly-cased login must now be rejected - proving the
     * controller reads config(), not env() directly (env() calls in a
     * controller are not affected by config() being set at runtime the
     * way this test does; a lingering env() read would make this
     * assertion fail).
     *
     * @return void
     */
    public function testLoginBecomesCaseSensitiveViaConfig(): void
    {
        config([ 'auth.login_case_sensitive' => true ]);

        User::create([
            'login'             => 'JDoe',
            'email'             => 'jdoe2@example.test',
            'email_verified_at' => now(),
            'password'          => 'Passw0rd!'
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ]);

        $response->assertStatus(400);
    }
}

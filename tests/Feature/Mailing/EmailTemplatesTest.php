<?php
/**
 * EmailTemplatesTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Mailing;

use Rivet\Tests\TestCase;
use Rivet\Data\Models\Auth\User;

/**
 * Regression guard for the white-label email layout
 * (resources/views/emails/layout.blade.php) and the 7 views that
 * extend it: each must render without error given the exact
 * variables its sending code actually passes (see the Mail::send()
 * call sites in AuthController/TwoFactorController/UserTrait/
 * LoginController). Also covers user/logins.blade.php's rename from
 * the old '.php' extension - a plain '.php' view is compiled by
 * Laravel's raw PHP engine, which does not understand Blade's
 * '@foreach'/'{{ }}' directives at all, so that file could never
 * have rendered correctly.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class EmailTemplatesTest extends TestCase
{
    /**
     * @return User
     */
    private function _user(): User
    {
        return User::create([
            'login' => 'jdoe', 'email' => 'jdoe@example.test',
            'email_verified_at' => now(), 'password' => 'Passw0rd!'
        ]);
    }

    /**
     * @return void
     */
    public function testForgotPasswordViewRenders(): void
    {
        $html = view('rivet::emails.auth.forgot', [
            'user' => $this->_user(), 'token' => 'tok123',
            'token_expires_at' => now()->addHour()
        ])->render();

        $this->assertStringContainsString('reset-password/tok123', $html);
    }

    /**
     * @return void
     */
    public function testPasswordCreationViewRenders(): void
    {
        $html = view('rivet::emails.auth.password', [
            'user' => $this->_user(), 'token' => 'tok456',
            'token_expires_at' => now()->addHour()
        ])->render();

        $this->assertStringContainsString('create-password/tok456', $html);
    }

    /**
     * @return void
     */
    public function testTwoFactorCodeViewRenders(): void
    {
        $html = view('rivet::emails.auth.two_factor_code', [
            'user' => $this->_user(), 'code' => '482913'
        ])->render();

        $this->assertStringContainsString('482913', $html);
    }

    /**
     * @return void
     */
    public function testEmailValidationViewRenders(): void
    {
        $html = view('rivet::emails.user.validate', [
            'user' => $this->_user(), 'token' => 'tok789'
        ])->render();

        $this->assertStringContainsString('verify-email/tok789', $html);
    }

    /**
     * @return void
     */
    public function testEmailValidatedViewShowsCheckInboxOnlyWhenPasswordIsNull(): void
    {
        $user = $this->_user();

        $html = view('rivet::emails.user.email', [ 'user' => $user ])->render();
        $this->assertStringNotContainsString(
            trans('rivet::mail.email_validated_check_inbox'), $html
        );

        $user->password = null;
        $html = view('rivet::emails.user.email', [ 'user' => $user ])->render();
        $this->assertStringContainsString(
            trans('rivet::mail.email_validated_check_inbox'), $html
        );
    }

    /**
     * @return void
     */
    public function testPasswordUpdatedViewRenders(): void
    {
        $html = view('rivet::emails.user.password', [
            'user' => $this->_user()
        ])->render();

        $this->assertStringContainsString(
            'Your password has just been changed', $html
        );
    }

    /**
     * Confirms the '.php' -> '.blade.php' rename actually fixed
     * something: a bare '.php' view containing '@foreach'/'{{ }}'
     * is not run through the Blade compiler at all, so this render
     * would previously have failed.
     *
     * @return void
     */
    public function testLoginsViewRendersEachLogin(): void
    {
        $html = view('rivet::emails.user.logins', [
            'logins' => [ 'jdoe', 'jdoe.pro' ]
        ])->render();

        $this->assertStringContainsString('jdoe', $html);
        $this->assertStringContainsString('jdoe.pro', $html);
    }

    /**
     * @return void
     */
    public function testBrandColorIsConfigurable(): void
    {
        config([ 'mail.brand_color' => '#ff0000' ]);

        $html = view('rivet::emails.user.password', [
            'user' => $this->_user()
        ])->render();

        $this->assertStringContainsString('#ff0000', $html);
    }
}

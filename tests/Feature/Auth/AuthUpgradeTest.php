<?php
/**
 * AuthUpgradeTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Rivet\Data\Models\Auth\TwoFactorMethod;
use Rivet\Data\Models\Auth\User;
use Rivet\Mail\BaseMail;
use Rivet\Tests\TestCase;

/**
 * AuthUpgradeTest
 *
 * Token issuance (login/refresh), login reminder email and the v1.3.0
 * upgrade migrations.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class AuthUpgradeTest extends TestCase
{
    /**
     * @return User
     */
    private function _createUser(): User
    {
        return User::create([
            'login'             => 'jdoe',
            'email'             => 'jdoe@example.test',
            'email_verified_at' => now(),
            'password'          => 'Passw0rd!'
        ]);
    }

    /**
     * @return object The migration instance
     */
    private function _migration(string $name): object
    {
        return require __DIR__."/../../../database/migrations/{$name}.php";
    }

    /**
     * Refresh used to ignore 'expiration_override' (login honoured it).
     *
     * @return void
     */
    public function testRefreshUsesTheSameExpirationAsLogin(): void
    {
        config([ 'sanctum.expiration' => 60, 'sanctum.expiration_override' => 5 ]);

        $this->_createUser();

        $login = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ])->json('data');

        $refresh = $this->withToken($login['token'])
            ->getJson('/api/auth/refresh')->assertStatus(200)->json('data');

        $expected = now()->addMinutes(5)->getTimestamp();

        $this->assertEqualsWithDelta($expected, strtotime($login['expires_at']), 5);
        $this->assertEqualsWithDelta($expected, strtotime($refresh['expires_at']), 5);
        $this->assertSame('jdoe', $refresh['user']['login']);
    }

    /**
     * @return void
     */
    public function testRefreshRevokesTheCurrentToken(): void
    {
        $user = $this->_createUser();

        $token = $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ])->json('data.token');

        $this->withToken($token)->getJson('/api/auth/refresh')->assertStatus(200);

        $this->assertSame(1, $user->tokens()->count());
    }

    /**
     * @return void
     */
    public function testLoginPrunesExpiredTokens(): void
    {
        $user = $this->_createUser();
        $user->createToken('expired', [ '*' ], now()->subMinute());

        $this->postJson('/api/auth/login', [
            'login' => 'jdoe', 'password' => 'Passw0rd!'
        ])->assertStatus(200);

        $this->assertSame(0, $user->tokens()->where('name', 'expired')->count());
    }

    /**
     * The login reminder used to be sent to MAIL_FROM_ADDRESS.
     *
     * @return void
     */
    public function testTheLoginReminderIsSentToTheRequestedAddress(): void
    {
        $this->_createUser();
        Mail::fake();

        $this->postJson('/api/auth/user/login', [ 'email' => 'JDoe@Example.test' ])
            ->assertStatus(200);

        Mail::assertSent(
            BaseMail::class,
            fn ($mail) => $mail->hasTo('jdoe@example.test') && $mail->logins === [ 'jdoe' ]
        );
    }

    /**
     * @return void
     */
    public function testNoLoginReminderIsSentForAnUnknownAddress(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/user/login', [ 'email' => 'nobody@example.test' ])
            ->assertStatus(200);

        Mail::assertNothingSent();
    }

    /**
     * Fresh installs no longer create the legacy columns.
     *
     * @return void
     */
    public function testUsersHaveNoLegacyPasswordTokenColumns(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'pwd_token'));
        $this->assertFalse(Schema::hasColumn('users', 'pwd_token_created_at'));
    }

    /**
     * Older databases still have them: dropped.
     *
     * @return void
     */
    public function testTheCleanupMigrationDropsLegacyColumns(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pwd_token')->nullable();
            $table->timestamp('pwd_token_created_at')->nullable();
        });

        $migration = $this->_migration(
            '2026_09_25_000000_drop_legacy_pwd_token_columns_from_users'
        );
        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'pwd_token'));
        $this->assertFalse(Schema::hasColumn('users', 'pwd_token_created_at'));

        // Idempotent.
        $migration->up();
        $this->assertTrue(Schema::hasTable('users'));
    }

    /**
     * Secrets stored in clear by v1.2.x are encrypted, once.
     *
     * @return void
     */
    public function testTheEncryptionMigrationEncryptsClearSecretsOnce(): void
    {
        $user = $this->_createUser();

        DB::table('user_two_factor_methods')->insert([
            'user_id' => $user->id, 'method' => 'totp',
            'secret' => 'JBSWY3DPEHPK3PXP', 'confirmed_at' => now(),
            'created_at' => now(), 'updated_at' => now()
        ]);

        $migration = $this->_migration('2026_09_25_000001_encrypt_two_factor_secrets');
        $migration->up();
        $once = DB::table('user_two_factor_methods')->value('secret');
        $migration->up();

        $this->assertSame($once, DB::table('user_two_factor_methods')->value('secret'));
        $this->assertSame('JBSWY3DPEHPK3PXP', Crypt::decryptString($once));
        $this->assertSame('JBSWY3DPEHPK3PXP', TwoFactorMethod::first()->secret);

        $migration->down();
        $this->assertSame(
            'JBSWY3DPEHPK3PXP', DB::table('user_two_factor_methods')->value('secret')
        );
    }
}

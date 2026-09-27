<?php
/**
 * Two-factor secrets encryption migration file
 *
 * PHP Version 8.1
 *
 * @category Migration
 * @package  Rivet\Database\Migrations
 * @license  https://opensource.org/licenses/MIT MIT License
 */
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TOTP secrets are now stored encrypted (APP_KEY): widen the column
     * and encrypt any secret still stored in clear.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('user_two_factor_methods', function (Blueprint $table) {
            $table->text('secret')->nullable()->change();
        });

        DB::table('user_two_factor_methods')->whereNotNull('secret')
            ->orderBy('id')->each(function (object $row) {
                if ($this->isEncrypted($row->secret)) {
                    return;
                }

                DB::table('user_two_factor_methods')->where('id', $row->id)
                    ->update([ 'secret' => Crypt::encryptString($row->secret) ]);
            });
    }

    /**
     * Decrypt the secrets back to clear text.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('user_two_factor_methods')->whereNotNull('secret')
            ->orderBy('id')->each(function (object $row) {
                if (!$this->isEncrypted($row->secret)) {
                    return;
                }

                DB::table('user_two_factor_methods')->where('id', $row->id)
                    ->update([ 'secret' => Crypt::decryptString($row->secret) ]);
            });

        Schema::table('user_two_factor_methods', function (Blueprint $table) {
            $table->string('secret')->nullable()->change();
        });
    }

    /**
     * Whether a value is already an APP_KEY payload.
     *
     * @param string $value The stored value
     *
     * @return bool
     */
    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};

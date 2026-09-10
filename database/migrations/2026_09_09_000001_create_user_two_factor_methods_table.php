<?php
/**
 * Two-factor methods migration file
 *
 * PHP Version 8.1
 *
 * @category Migration
 * @package  Rivet\Database\Migrations
 * @license  https://opensource.org/licenses/MIT MIT License
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per enrolled two-factor method per user - a user may
     * have both TOTP and email OTP enrolled at once, with either used
     * to satisfy verification.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('user_two_factor_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method'); // 'totp' | 'email'
            $table->string('secret')->nullable(); // TOTP only
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique([ 'user_id', 'method' ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('user_two_factor_methods');
    }
};

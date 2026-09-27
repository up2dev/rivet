<?php
/**
 * Legacy password token columns cleanup migration file
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
     * Password tokens live in the 'tokens' table: drop the old columns
     * (the tokens migration used removeColumn(), which never hit the DB).
     *
     * @return void
     */
    public function up(): void
    {
        $columns = array_values(array_filter(
            [ 'pwd_token', 'pwd_token_created_at' ],
            fn (string $column) => Schema::hasColumn('users', $column)
        ));

        if (!empty($columns)) {
            Schema::table('users', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    /**
     * Restore the legacy columns (empty).
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pwd_token')) {
                $table->string('pwd_token')->nullable();
            }

            if (!Schema::hasColumn('users', 'pwd_token_created_at')) {
                $table->timestamp('pwd_token_created_at')->nullable();
            }
        });
    }
};

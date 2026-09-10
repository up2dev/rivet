<?php
// A new migration rather than editing the original
// 2022_11_19_153828_create_sendmails_table.php, which may already
// have run in production. 'sent_at' must be nullable for a "pending"
// (not yet sent) row to exist at all.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('sendmails', function (Blueprint $table) {
            $table->dateTime('sent_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // Deliberately not reverting to nullable(false): any row with
        // sent_at = NULL at that point is a genuinely pending email,
        // and forcing the constraint back would mean either crashing
        // on that data or silently discarding it - not a migration's
        // call to make. A real rollback needs a decision about those
        // rows first.
    }
};

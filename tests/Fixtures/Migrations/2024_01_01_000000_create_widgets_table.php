<?php
// Fixture table for tests only. 'secret_internal_flag' is deliberately
// not in the Widget model's $fillable, to exercise mass-assignment
// protection.

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
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('secret_internal_flag')->nullable();
            $table->foreignId('category_id')->nullable()->constrained(
                'widget_categories'
            );
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};

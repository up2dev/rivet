<?php
// Fixture table for MakeCrudCommandTest only: a small table with a
// foreign key (to exercise relation detection), a unique varchar
// column (to exercise the unique:/max: validation rules), and a
// nullable text column (to exercise the nullable/required split).

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
        Schema::create('crud_gen_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120)->unique();
            $table->text('body')->nullable();
            $table->foreignId('author_id')->constrained('users');
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
        Schema::dropIfExists('crud_gen_articles');
    }
};

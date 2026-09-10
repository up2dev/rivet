<?php

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
        Schema::create('crontasks', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->string('command');
            $table->char('minute')->default('*');
            $table->char('hour')->default('*');
            $table->char('day')->default('*');
            $table->char('month')->default('*');
            $table->char('year')->default('*');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('crontasks');
    }
};

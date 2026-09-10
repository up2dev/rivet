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
        // # mongo passe pas dans le down
        Schema::connection('mongodb')->drop('logs');
        Schema::connection('mongodb')->create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('process');
            $table->string('source');
            $table->string('code');
            $table->jsonb('data');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // Schema::connection('mongodb')->drop('logs');
    }
};

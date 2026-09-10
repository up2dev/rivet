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
        Schema::create('taxonomy_values', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->string('value');
            $table->integer('order')->nullable();
            $table->foreignId('taxonomy_id')->references('id')->on(
                'taxonomies'
            )->onDelete('cascade');
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
        Schema::dropIfExists('taxonomy_values');
    }
};

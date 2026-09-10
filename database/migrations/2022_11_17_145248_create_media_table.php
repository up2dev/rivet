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
        // The noun media can be countable or uncountable : https://www.wordhippo.com/what-is/the-plural-of/media.html
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->string('name');
            $table->string('comments')->nullable();
            $table->unsignedInteger('max_chunk');
            $table->string('mimetype');
            $table->unsignedSmallInteger('min_width')->nullable();
            $table->unsignedSmallInteger('max_width')->nullable();
            $table->unsignedSmallInteger('min_height')->nullable();
            $table->unsignedSmallInteger('max_height')->nullable();
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
        Schema::dropIfExists('media');
    }
};

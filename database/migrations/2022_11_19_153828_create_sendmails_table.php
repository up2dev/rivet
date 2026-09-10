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
        Schema::create('sendmails', function (Blueprint $table) {
            $table->id();
            $table->string('token');
            $table->string('from');
            $table->string('to');
            $table->string('subject');
            $table->text('content');
            $table->dateTime('sent_at');
            $table->boolean('is_success')->nullable();
            // $table->string('error');
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
        Schema::dropIfExists('sendmails');
    }
};

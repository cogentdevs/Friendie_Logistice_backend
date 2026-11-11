<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_ad_room_messages', function (Blueprint $table) {
            $table->id();
            $table->integer('post_ad_room_id');
            $table->integer('from_user_id');
            $table->integer('to_user_id');
            $table->text('message');
            $table->string('message_on');
            $table->string('read_on')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_ad_room_messages');
    }
};

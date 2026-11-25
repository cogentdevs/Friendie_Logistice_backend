<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_notifications', function (Blueprint $table) {
            $table->engine='InnoDB';
            $table->id();
            $table->unsignedBigInteger('recipient_user_id');
            $table->unsignedBigInteger('deal_id');
            $table->string('event',32);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['deal_id','event','recipient_user_id'],'deal_notifications_event_recipient_unique');
            $table->index(['recipient_user_id','id']);
            $table->index(['recipient_user_id','read_at','id']);
            // No cascade: notification visibility never deletes permanent deal history.
        });
    }

    public function down(): void
    {
        // Manual rollback removes notification/read history; never run on a live table blindly.
        Schema::dropIfExists('deal_notifications');
    }
};

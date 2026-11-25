<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_deals', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            // Deliberately no cascading foreign keys: retain history after legacy deletion.
            $table->unsignedBigInteger('sender_listing_id');
            $table->unsignedBigInteger('carrier_listing_id');
            $table->unsignedBigInteger('sender_user_id');
            $table->unsignedBigInteger('carrier_user_id');
            $table->unsignedBigInteger('initiated_by');
            $table->unsignedInteger('requested_kg');
            $table->unsignedInteger('agreed_kg');
            $table->string('status', 24);
            // NULL for terminal requests; UNIQUE permits later attempts for the same pair.
            $table->string('active_pair', 48)->nullable()->unique();
            $table->json('listing_snapshot');
            foreach (['accepted_at', 'acceptance_expires_at', 'confirmed_at', 'rejected_at',
                'cancelled_at', 'picked_up_at', 'completed_at'] as $column) {
                $table->timestamp($column)->nullable();
            }
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamps();
            $table->index(['sender_listing_id', 'status']);
            $table->index(['carrier_listing_id', 'status']);
            $table->index(['sender_user_id', 'id']);
            $table->index(['carrier_user_id', 'id']);
            $table->index(['status', 'acceptance_expires_at']);
        });
    }

    public function down(): void
    {
        // Manual rollback destroys deal history; do not run against a live populated table.
        Schema::dropIfExists('listing_deals');
    }
};

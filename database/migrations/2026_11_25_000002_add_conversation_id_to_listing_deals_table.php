<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_deals', function (Blueprint $table) {
            $table->unsignedBigInteger('conversation_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('listing_deals', function (Blueprint $table) {
            $table->dropUnique(['conversation_id']);
            $table->dropColumn('conversation_id');
        });
    }
};

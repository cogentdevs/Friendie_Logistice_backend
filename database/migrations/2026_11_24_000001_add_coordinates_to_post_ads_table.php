<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_ads', function (Blueprint $table) {
            $table->decimal('from_latitude', 10, 7)->nullable();
            $table->decimal('from_longitude', 10, 7)->nullable();
            $table->decimal('to_latitude', 10, 7)->nullable();
            $table->decimal('to_longitude', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('post_ads', function (Blueprint $table) {
            $table->dropColumn(['from_latitude', 'from_longitude', 'to_latitude', 'to_longitude']);
        });
    }
};

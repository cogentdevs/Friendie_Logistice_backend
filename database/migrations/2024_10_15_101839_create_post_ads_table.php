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
        Schema::create('post_ads', function (Blueprint $table) {
            $table->id();
            $table->integer('category_id')->nullable();
            $table->string('ad_type');
            $table->string('title');
            $table->string('image')->nullable();
            $table->longText('description')->nullable();
            $table->integer('weight')->nullable();
            $table->double('weight_price')->nullable();
            $table->integer('from_country_id')->nullable();
            $table->integer('from_city_id')->nullable();
            $table->integer('from_area')->nullable();
            $table->integer('to_country_id')->nullable();
            $table->integer('to_city_id')->nullable();
            $table->integer('to_area')->nullable();
            $table->date('departure_on')->nullable();
            $table->date('reached_on')->nullable();
            $table->tinyinteger('show_contact_info');
            $table->tinyinteger('isActive')->default(1);
            $table->tinyinteger('isFeatured')->default(0);
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_ads');
    }
};

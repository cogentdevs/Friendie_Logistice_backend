<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->unique(['deal_id','from_user_id'],'reviews_deal_reviewer_unique');
            $table->index(['post_ads_id','id'],'reviews_listing_pagination_index');
        });
    }

    public function down(): void
    {
        // Removing this binding loses completed-deal provenance; never roll back blindly.
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_deal_reviewer_unique');
            $table->dropIndex('reviews_listing_pagination_index');
            $table->dropColumn('deal_id');
        });
    }
};

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
        Schema::table('products', function (Blueprint $table) {
            $table->index('status');
            $table->index('is_featured');
            $table->index('price');
            $table->index('cat_id');
            $table->index('brand_id');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->index('status');
            $table->index('post_cat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['is_featured']);
            $table->dropIndex(['price']);
            $table->dropIndex(['cat_id']);
            $table->dropIndex(['brand_id']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['post_cat_id']);
        });
    }
};

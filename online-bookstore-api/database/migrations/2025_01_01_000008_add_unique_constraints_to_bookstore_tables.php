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
        // A user can only have a given book in their cart once.
        Schema::table('carts', function (Blueprint $table) {
            $table->unique(['user_id', 'book_id']);
        });

        // A user can only wishlist a given book once.
        Schema::table('wishlists', function (Blueprint $table) {
            $table->unique(['user_id', 'book_id']);
        });

        // A user can only review a given book once.
        Schema::table('reviews', function (Blueprint $table) {
            $table->unique(['user_id', 'book_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'book_id']);
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'book_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'book_id']);
        });
    }
};

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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();

            // Polymorphic creator (user or admin)
            $table->unsignedBigInteger('creator_id');
            $table->string('creator_type');
            $table->index(['creator_id', 'creator_type']);

            $table->string('title');
            $table->text('description')->nullable();

            // Timestamps - nullable to avoid MySQL default value issues
            $table->timestamp('auction_start_time')->nullable();
            $table->timestamp('auction_end_time')->nullable();

            $table->decimal('starting_bid', 15, 2);
            $table->decimal('reserve_price', 15, 2)->nullable();
            $table->decimal('buy_now_price', 15, 2)->nullable();

            $table->enum('bid_increment', ['Auto', 'fixed'])->default('Auto');
            $table->boolean('auto_extend')->default(false);
            $table->boolean('featured')->default(false);

            $table->json('promotional_tags')->nullable();
            $table->string('auth_certificate')->nullable();

            // Polymorphic winner (user or admin)
            $table->unsignedBigInteger('winner_id')->nullable();
            $table->string('winner_type')->nullable();

            $table->enum('status', ['pending', 'cancelled', 'rejected', 'approved', 'suspended'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};

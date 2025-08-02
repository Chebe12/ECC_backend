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
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->onDelete('cascade');

            // Morph relation for bidder: either User or Customer
            $table->unsignedBigInteger('bidder_id');
            $table->string('bidder_type');

            $table->decimal('amount', 12, 2); // Bid amount
            $table->boolean('is_auto')->default(false);

            // For auto-bid settings
            $table->decimal('auto_max_bid', 12, 2)->nullable(); // Max limit
            $table->decimal('auto_increment', 12, 2)->nullable(); // Step

            $table->timestamps();

            $table->index(['bidder_id', 'bidder_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};

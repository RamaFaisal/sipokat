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
        Schema::create('receive_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receive_order_id')->constrained('receive_orders')->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();
            $table->string('medicine_name');
            $table->integer('qty');
            $table->decimal('price', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receive_order_items');
    }
};

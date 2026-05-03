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
        Schema::create('receive_orders', function (Blueprint $table) {
            $table->id();
            $table->string('receive_order_number')->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('receive_date');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->boolean('late_arrival')->default(false);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receive_orders');
    }
};

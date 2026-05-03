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
        Schema::create('medicine_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();
            $table->integer('qty');
            $table->enum('type_account', ['D', 'C'])->comment('D = Debit/Masuk, C = Credit/Keluar');
            $table->date('date');
            $table->foreignId('receive_order_id')->nullable()->constrained('receive_orders')->nullOnDelete();
            $table->foreignId('medicine_stock_opname_id')->nullable()->constrained('medicine_stock_opnames')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('hpp', 15, 2)->default(0)->comment('Harga Pokok Penjualan');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['medicine_id', 'type_account']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicine_stocks');
    }
};

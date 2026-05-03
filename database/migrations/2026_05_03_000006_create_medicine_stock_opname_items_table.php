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
        Schema::create('medicine_stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->integer('qty');
            $table->decimal('hpp', 15, 2)->default(0);
            $table->enum('type_account', ['D', 'C'])->comment('D = Debit, C = Credit');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicine_stock_opname_items');
    }
};

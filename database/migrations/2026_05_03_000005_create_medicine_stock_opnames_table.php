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
        Schema::create('medicine_stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number')->unique();
            $table->date('opname_date');
            $table->enum('status', ['in_stock', 'not_in_stock'])->default('in_stock');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicine_stock_opnames');
    }
};

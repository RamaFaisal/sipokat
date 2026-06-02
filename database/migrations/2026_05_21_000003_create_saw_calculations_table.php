<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saw_calculations', function (Blueprint $table) {
            $table->id();
            $table->timestamp('calculated_at')->useCurrent();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('trigger_type', ['manual', 'scheduled'])->default('manual');
            $table->json('criteria_snapshot')->nullable();
            $table->unsignedInteger('total_alternatives')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('calculated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saw_calculations');
    }
};

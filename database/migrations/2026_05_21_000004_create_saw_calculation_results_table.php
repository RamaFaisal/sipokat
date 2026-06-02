<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saw_calculation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saw_calculation_id')->constrained('saw_calculations')->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();

            $table->decimal('c1_raw', 15, 2)->nullable();
            $table->decimal('c2_raw', 15, 2)->nullable();
            $table->decimal('c3_raw', 15, 2)->nullable();
            $table->decimal('c4_raw', 15, 2)->nullable();

            $table->unsignedTinyInteger('c1_score')->default(0);
            $table->unsignedTinyInteger('c2_score')->default(0);
            $table->unsignedTinyInteger('c3_score')->default(0);
            $table->unsignedTinyInteger('c4_score')->default(0);

            $table->decimal('c1_norm', 8, 6)->default(0);
            $table->decimal('c2_norm', 8, 6)->default(0);
            $table->decimal('c3_norm', 8, 6)->default(0);
            $table->decimal('c4_norm', 8, 6)->default(0);

            $table->decimal('preference_value', 8, 6)->default(0);
            $table->unsignedInteger('rank')->default(0);

            $table->timestamps();

            $table->index(['saw_calculation_id', 'rank']);
            $table->index('medicine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saw_calculation_results');
    }
};

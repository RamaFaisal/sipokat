<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receive_order_items', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('price');
            $table->date('manufacture_date')->nullable()->after('batch_number');
            $table->date('expired_date')->nullable()->after('manufacture_date');

            $table->index('expired_date');
            $table->index(['medicine_id', 'expired_date']);
        });
    }

    public function down(): void
    {
        Schema::table('receive_order_items', function (Blueprint $table) {
            $table->dropIndex(['medicine_id', 'expired_date']);
            $table->dropIndex(['expired_date']);
            $table->dropColumn(['batch_number', 'manufacture_date', 'expired_date']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaspersky_statuses', function (Blueprint $table) {
            $table->foreignId('computer_workbook_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('computer_workbooks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kaspersky_statuses', function (Blueprint $table) {
            $table->dropForeign(['computer_workbook_id']);
            $table->dropColumn('computer_workbook_id');
        });
    }
};

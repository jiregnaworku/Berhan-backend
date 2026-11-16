<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_workbooks', function (Blueprint $table) {
            if (! Schema::hasColumn('computer_workbooks', 'computer_model')) {
                $table->string('computer_model', 100)->nullable();
            }

            if (! Schema::hasColumn('computer_workbooks', 'cheque_scanner')) {
                $table->boolean('cheque_scanner')->default(false);
            }

            // Issue / remark written when registering a computer (skipped if it already exists)
            if (! Schema::hasColumn('computer_workbooks', 'remark')) {
                $table->string('remark', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('computer_workbooks', function (Blueprint $table) {
            if (Schema::hasColumn('computer_workbooks', 'computer_model')) {
                $table->dropColumn('computer_model');
            }

            if (Schema::hasColumn('computer_workbooks', 'cheque_scanner')) {
                $table->dropColumn('cheque_scanner');
            }
        });
    }
};
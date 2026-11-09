<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_workbooks', function (Blueprint $table) {
            if (!Schema::hasColumn('computer_workbooks', 'kaspersky_status')) {
                $table->string('kaspersky_status', 60)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('computer_workbooks', function (Blueprint $table) {
            if (Schema::hasColumn('computer_workbooks', 'kaspersky_status')) {
                $table->dropColumn('kaspersky_status');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_workbooks', function (Blueprint $table) {
            $table->string('computer_job', 20)->nullable()->after('computer_name');
        });
    }

    public function down(): void
    {
        Schema::table('computer_workbooks', function (Blueprint $table) {
            $table->dropColumn('computer_job');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mto_statuses', function (Blueprint $table) {
            // Only used when the status is "Not working"
            $table->string('issue_type', 40)->nullable()->after('status');
            $table->string('issue_detail', 60)->nullable()->after('issue_type');
        });
    }

    public function down(): void
    {
        Schema::table('mto_statuses', function (Blueprint $table) {
            $table->dropColumn(['issue_type', 'issue_detail']);
        });
    }
};
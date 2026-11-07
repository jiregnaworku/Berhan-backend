<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_related_infos', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('it_related_infos')->cascadeOnDelete();
            $table->string('entry_type', 20)->default('member')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('it_related_infos', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'entry_type']);
        });
    }
};

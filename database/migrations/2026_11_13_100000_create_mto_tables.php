<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per branch: is MTO editing turned on for its branch manager?
        Schema::create('mto_branch_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique('branch_id');
        });

        // Reports sent by branch managers, answered ("accepted") by the ambassador
        Schema::create('mto_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('mto', 40);
            $table->string('status', 40);
            $table->text('remark')->nullable();
            $table->string('submitted_by');
            $table->string('review_state', 20)->default('pending');
            $table->string('accepted_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mto_statuses');
        Schema::dropIfExists('mto_branch_accesses');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preventive_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ambassador_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('operation_date');
            $table->string('document_path');
            $table->string('document_name');
            $table->timestamps();
            $table->index(['operation_date', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_operations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computer_workbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('created_by', 120)->index();
            $table->string('ambassador_name', 120)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('computer_name', 120);
            $table->ipAddress('managed_kasp_ip')->nullable();
            $table->string('version', 60)->nullable();
            $table->string('status', 60)->nullable();
            $table->string('network_agent', 60)->nullable();
            $table->string('endpoint_protection', 60)->nullable();
            $table->string('remark', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computer_workbooks');
    }
};

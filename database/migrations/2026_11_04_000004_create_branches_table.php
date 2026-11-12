<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_name', 120);
            $table->string('branch_location')->nullable();
            $table->string('ambassador_name', 120)->nullable();
            $table->string('ambassador_phone', 30)->nullable();
            $table->string('backup_name', 120)->nullable();
            $table->string('backup_phone', 30)->nullable();
            $table->string('manager_name', 120)->nullable();
            $table->string('manager_phone', 30)->nullable();
            $table->string('assistant_name', 120)->nullable();
            $table->string('assistant_mobile', 30)->nullable();
            $table->ipAddress('display_tv_ip')->nullable();
            $table->ipAddress('kaspersky_server_ip')->nullable();
            $table->unsignedInteger('working_computers');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
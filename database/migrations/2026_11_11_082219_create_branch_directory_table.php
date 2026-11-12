<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_directory', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 120);
            $table->string('branch_type', 20)->default('Branch');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_directory');
    }
};
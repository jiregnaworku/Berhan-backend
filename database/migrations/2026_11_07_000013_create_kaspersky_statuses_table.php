<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspersky_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('created_by', 120)->index();
            $table->string('network_agent', 60);
            $table->string('endpoint', 60);
            $table->enum('status', ['Updated', 'Not updated']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspersky_statuses');
    }
};

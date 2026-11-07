<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('branch_name', 120);
            $table->string('reason', 255);
            $table->text('remark')->nullable();
            $table->string('ethio_telecom_side', 120)->nullable();
            $table->string('ethio_telecom_frequency', 120)->nullable();
            $table->string('elpha_side', 120)->nullable();
            $table->string('elpha_frequency', 120)->nullable();
            $table->date('incident_date');
            $table->string('created_by', 120);
            $table->timestamps();

            $table->index('incident_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_registrations');
    }
};

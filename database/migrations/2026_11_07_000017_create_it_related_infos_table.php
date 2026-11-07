<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_related_infos', function (Blueprint $table) {
            $table->id();
            $table->string('category', 120);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('team_phone', 30)->nullable();
            $table->string('office_phone', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_related_infos');
    }
};

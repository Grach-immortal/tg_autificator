<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_codes', function (Blueprint $table) {
            $table->id();
            $table->string('id_ad')->unique();
            $table->string('code', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_codes');
    }
};

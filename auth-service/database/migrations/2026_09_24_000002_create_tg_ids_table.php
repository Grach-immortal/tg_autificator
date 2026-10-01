<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tg_ids', function (Blueprint $table) {
            $table->id();
            $table->string('id_ad');
            $table->string('id_tg')->unique();

            $table->index('id_ad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tg_ids');
    }
};

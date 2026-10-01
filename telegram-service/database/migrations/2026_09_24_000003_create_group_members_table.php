<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->string('id_tg')->unique();
            $table->boolean('in_group')->default(true);
            $table->boolean('is_bot')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};

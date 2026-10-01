<?php

use App\Enums\EmployeeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_codes', function (Blueprint $table) {
            $table->string('status_employee')->default(EmployeeStatus::Active->value);
        });
    }

    public function down(): void
    {
        Schema::table('auth_codes', function (Blueprint $table) {
            $table->dropColumn('status_employee');
        });
    }
};

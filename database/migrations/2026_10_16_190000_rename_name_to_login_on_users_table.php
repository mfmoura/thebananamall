<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('name', 'login');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('login');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_login_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('login', 'name');
        });
    }
};

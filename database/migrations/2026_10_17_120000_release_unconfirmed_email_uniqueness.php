<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX users_email_active_unique');
        DB::statement('CREATE UNIQUE INDEX users_email_confirmed_unique ON users (email) WHERE deleted_at IS NULL AND email_validado = true');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX users_email_confirmed_unique');
        DB::statement('CREATE UNIQUE INDEX users_email_active_unique ON users (email) WHERE deleted_at IS NULL');
    }
};

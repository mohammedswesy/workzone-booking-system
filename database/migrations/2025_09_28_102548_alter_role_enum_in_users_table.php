<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize invalid / null roles before any column change.
        DB::table('users')
            ->where(function ($query) {
                $query->whereNotIn('role', ['user', 'owner', 'admin'])
                    ->orWhereNull('role');
            })
            ->update(['role' => 'user']);

        $driver = Schema::getConnection()->getDriverName();

        // Keep role as a plain string column (cast to PHP Enum in the app).
        // Avoid DB-level ENUM so migrations stay SQLite-safe.
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(255) NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(255) NOT NULL DEFAULT 'user'");
        }
    }
};

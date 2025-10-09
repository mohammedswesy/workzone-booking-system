<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // طبّع أي قيم خارجية قبل التحويل (وقائي)
        DB::table('users')
            ->whereNotIn('role', ['user','owner','admin'])
            ->orWhereNull('role')
            ->update(['role' => 'user']);

        // حوّل العمود إلى ENUM محدد القيم
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('user','owner','admin') NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        // رجّعه إلى VARCHAR لو صار rollback
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(255) NOT NULL DEFAULT 'user'");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            //
            // نضيف عمود owner_id مربوط بجدول users
            $table->foreignId('owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('id'); // يجي بعد id
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            //
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};

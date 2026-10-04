<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->time('opening_time')->default('08:00:00')->after('price_per_hour');
            $table->time('closing_time')->default('22:00:00')->after('opening_time');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('start_at')->nullable()->after('workspace_id');
            $table->timestamp('end_at')->nullable()->after('start_at');
            $table->string('payment_status', 32)->default('unpaid')->after('status');
        });

        $this->backfillBookingSchedule();
        $this->normalizeBookingStatusColumn();

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'start_at', 'end_at']);
            $table->index(['payment_status']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->index(['workspace_id', 'is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'is_active', 'starts_at', 'ends_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['workspace_id', 'status']);
            $table->dropIndex(['workspace_id', 'start_at', 'end_at']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['start_at', 'end_at', 'payment_status']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['opening_time', 'closing_time']);
        });
    }

    private function backfillBookingSchedule(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('
                UPDATE bookings
                SET
                    start_at = COALESCE(start_at, created_at),
                    end_at = COALESCE(end_at, DATE_ADD(COALESCE(start_at, created_at), INTERVAL GREATEST(hours, 1) HOUR)),
                    payment_status = CASE
                        WHEN status = \'paid\' THEN \'paid\'
                        ELSE COALESCE(payment_status, \'unpaid\')
                    END,
                    status = CASE
                        WHEN status = \'paid\' THEN \'confirmed\'
                        ELSE status
                    END
            ');

            return;
        }

        // SQLite / others: row-by-row backfill (safe for tests & small datasets).
        foreach (DB::table('bookings')->orderBy('id')->cursor() as $booking) {
            $hours = max((int) $booking->hours, 1);
            $start = $booking->start_at ?? $booking->created_at;
            $end = $booking->end_at ?? date('Y-m-d H:i:s', strtotime($start.' +'.$hours.' hours'));
            $status = $booking->status === 'paid' ? 'confirmed' : $booking->status;
            $payment = $booking->status === 'paid' ? 'paid' : ($booking->payment_status ?? 'unpaid');

            DB::table('bookings')->where('id', $booking->id)->update([
                'start_at' => $start,
                'end_at' => $end,
                'status' => $status,
                'payment_status' => $payment,
            ]);
        }
    }

    private function normalizeBookingStatusColumn(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Drop DB-level ENUM; app casts to PHP Enum instead.
            DB::statement("ALTER TABLE `bookings` MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'pending'");

            return;
        }

        if ($driver === 'sqlite') {
            // SQLite ENUM is a CHECK constraint — rebuild as plain string.
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('status_tmp', 32)->default('pending');
            });

            DB::table('bookings')->update([
                'status_tmp' => DB::raw('status'),
            ]);

            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('status');
            });

            Schema::table('bookings', function (Blueprint $table) {
                $table->renameColumn('status_tmp', 'status');
            });
        }
    }
};

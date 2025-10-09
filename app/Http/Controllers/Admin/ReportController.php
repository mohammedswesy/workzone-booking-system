<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from   = $request->date('from');
        $to     = $request->date('to');
        $status = $request->string('status')->toString(); // '', pending, paid, cancelled

        $bookings = Booking::query()
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to))
            ->when($status, fn($q) => $q->where('status', $status));

        // KPIs
        $kpis = [
            'users'      => User::count(),
            'owners'     => User::where('role','owner')->count(),
            'workspaces' => Workspace::count(),
            'bookings'   => (clone $bookings)->count(),
            'revenue'    => (clone $bookings)->where('status','paid')->sum('total_price'),
        ];

        // أعلى المساحات حجزًا
        $topWorkspaces = (clone $bookings)
            ->select('workspace_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(total_price) as sum'))
            ->groupBy('workspace_id')
            ->orderByDesc('cnt')
            ->with('workspace:id,name')
            ->take(10)
            ->get()
            ->map(fn($r) => [
                'id'    => $r->workspace_id,
                'name'  => $r->workspace?->name ?? '—',
                'count' => (int) $r->cnt,
                'sum'   => (float) $r->sum,
            ]);

        // سلسلة زمنية (حجوزات/يوم)
        $series = (clone $bookings)
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'))
            ->groupBy('d')->orderBy('d')
            ->get();

        return Inertia::render('Admin/Reports/Index', [
            'filters' => [
                'from'   => $from?->toDateString(),
                'to'     => $to?->toDateString(),
                'status' => $status,
            ],
            'kpis'          => $kpis,
            'series'        => $series,
            'topWorkspaces' => $topWorkspaces,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Csv\CsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $bookings = $this->bookingQuery($filters);

        $bookingCount = (clone $bookings)->count();
        $revenue = (clone $bookings)->where('payment_status', PaymentStatus::Paid)->sum('total_price');
        $avg = $bookingCount > 0 ? round(((float) $revenue) / $bookingCount, 2) : 0;

        $topWorkspaces = (clone $bookings)
            ->select('workspace_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(total_price) as sum'))
            ->groupBy('workspace_id')
            ->orderByDesc('cnt')
            ->with(['workspace:id,name,owner_id,venue_id', 'workspace.venue:id,name'])
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->workspace_id,
                'name' => trim(($r->workspace?->venue?->name ? $r->workspace->venue->name.' — ' : '').($r->workspace?->name ?? '—')),
                'venue' => $r->workspace?->venue?->name,
                'unit' => $r->workspace?->name,
                'count' => (int) $r->cnt,
                'sum' => (float) $r->sum,
            ]);

        $topOwners = Booking::query()
            ->join('workspaces', 'workspaces.id', '=', 'bookings.workspace_id')
            ->join('users', 'users.id', '=', 'workspaces.owner_id')
            ->when($filters['from'], fn ($q) => $q->whereDate('bookings.created_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('bookings.created_at', '<=', $filters['to']))
            ->when($filters['status'], fn ($q) => $q->where('bookings.status', $filters['status']))
            ->when($filters['workspace_id'], fn ($q) => $q->where('bookings.workspace_id', $filters['workspace_id']))
            ->when($filters['owner_id'], fn ($q) => $q->where('workspaces.owner_id', $filters['owner_id']))
            ->select(
                'workspaces.owner_id',
                'users.name',
                DB::raw('COUNT(bookings.id) as cnt'),
                DB::raw('SUM(bookings.total_price) as sum')
            )
            ->groupBy('workspaces.owner_id', 'users.name')
            ->orderByDesc('cnt')
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->owner_id,
                'name' => $r->name,
                'count' => (int) $r->cnt,
                'sum' => (float) $r->sum,
            ]);

        $series = (clone $bookings)
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'), DB::raw('SUM(total_price) as revenue'))
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        return Inertia::render('Admin/Reports/Index', [
            'filters' => $filters,
            'kpis' => [
                'users' => User::count(),
                'owners' => User::where('role', 'owner')->count(),
                'workspaces' => Workspace::count(),
                'bookings' => $bookingCount,
                'revenue' => (float) $revenue,
                'average_booking_value' => $avg,
            ],
            'series' => $series,
            'topWorkspaces' => $topWorkspaces,
            'topOwners' => $topOwners,
            'workspaces' => Workspace::orderBy('name')->get(['id', 'name', 'owner_id']),
            'owners' => User::where('role', 'owner')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        CsvExporter::applyLocale($request);

        $filters = $this->filters($request);
        $query = $this->bookingQuery($filters)
            ->with([
                'workspace:id,name,owner_id,venue_id',
                'workspace.venue:id,name',
                'workspace.owner:id,name',
                'user:id,name,email',
                'payments' => fn ($q) => $q->latest()->with('platformPaymentMethod:id,type,label'),
            ])
            ->orderBy('id');

        $headers = [
            CsvExporter::label('booking_id'),
            CsvExporter::label('venue'),
            CsvExporter::label('workspace'),
            CsvExporter::label('owner'),
            CsvExporter::label('user'),
            CsvExporter::label('email'),
            CsvExporter::label('seats'),
            CsvExporter::label('booking_status'),
            CsvExporter::label('payment_status'),
            CsvExporter::label('payment_method'),
            CsvExporter::label('payment_reference'),
            CsvExporter::label('amount'),
            CsvExporter::label('start_at'),
            CsvExporter::label('end_at'),
            CsvExporter::label('created_at'),
        ];

        $filename = 'bookings-report-'.now()->format('Ymd-His').'.csv';

        return CsvExporter::download($filename, $headers, function (callable $write) use ($query) {
            $query->chunkById(200, function ($chunk) use ($write) {
                foreach ($chunk as $booking) {
                    /** @var Booking $booking */
                    $payment = $booking->payments->first();
                    $methodRaw = $payment?->platformPaymentMethod?->type?->value
                        ?? $payment?->platformPaymentMethod?->type
                        ?? data_get($payment?->metadata, 'method')
                        ?? ($payment?->provider?->value ?? $payment?->provider);
                    $methodLabel = $payment?->platformPaymentMethod?->label
                        ?: CsvExporter::statusLabel('method', $methodRaw);
                    $reference = $payment?->transfer_reference ?: ($payment?->reference ?? '');

                    $write([
                        $booking->id,
                        $booking->workspace?->venue?->name,
                        $booking->workspace?->name,
                        $booking->workspace?->owner?->name,
                        $booking->user?->name,
                        $booking->user?->email,
                        $booking->seats,
                        CsvExporter::statusLabel('booking', $booking->status),
                        CsvExporter::statusLabel('payment', $booking->payment_status),
                        $methodLabel,
                        $reference,
                        CsvExporter::formatMoney($booking->total_price),
                        CsvExporter::formatDateTime($booking->start_at),
                        CsvExporter::formatDateTime($booking->end_at),
                        CsvExporter::formatDateTime($booking->created_at),
                    ]);
                }
            });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'status' => $request->string('status')->toString() ?: null,
            'workspace_id' => $request->integer('workspace_id') ?: null,
            'owner_id' => $request->integer('owner_id') ?: null,
        ];
    }

    private function bookingQuery(array $filters)
    {
        return Booking::query()
            ->when($filters['from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when($filters['status'], fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['workspace_id'], fn ($q) => $q->where('workspace_id', $filters['workspace_id']))
            ->when($filters['owner_id'], fn ($q) => $q->whereHas(
                'workspace',
                fn ($w) => $w->where('owner_id', $filters['owner_id'])
            ));
    }
}

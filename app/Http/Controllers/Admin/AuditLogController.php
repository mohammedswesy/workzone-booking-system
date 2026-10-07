<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\AppTimezone;
use App\Support\Csv\CsvExporter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $action = $request->string('action')->toString();
        $actor = $request->integer('actor_id') ?: null;

        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($actor, fn ($q) => $q->where('actor_id', $actor))
            ->latest('created_at')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->actor?->only('id', 'name', 'email'),
                'actor_role' => $log->actor_role,
                'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
                'subject_id' => $log->subject_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip' => $log->ip,
                'created_at' => AppTimezone::formatDisplay($log->created_at),
                'created_at_utc' => $log->created_at?->utc()->toIso8601String(),
            ]);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'action' => $action,
                'actor_id' => $actor,
            ],
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $action = $request->string('action')->toString();
        $actor = $request->integer('actor_id') ?: null;

        $rows = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($actor, fn ($q) => $q->where('actor_id', $actor))
            ->latest('created_at')
            ->limit(5000)
            ->get();

        $headers = ['id', 'created_at_local', 'action', 'actor', 'role', 'subject', 'ip', 'old_values', 'new_values'];

        $exporter = CsvExporter::download('audit-logs.csv', $headers, function ($write) use ($rows) {
            foreach ($rows as $log) {
                $write([
                    $log->id,
                    AppTimezone::formatDisplay($log->created_at, 'Y-m-d H:i:s'),
                    $log->action,
                    $log->actor?->email,
                    $log->actor_role,
                    trim(($log->subject_type ? class_basename($log->subject_type) : '').'#'.$log->subject_id, '#'),
                    $log->ip,
                    json_encode($log->old_values),
                    json_encode($log->new_values),
                ]);
            }
        });

        return $exporter;
    }
}

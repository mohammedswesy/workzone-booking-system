<?php

namespace App\Http\Controllers\Owner;

use App\Enums\AvailabilityExceptionType;
use App\Enums\AvailabilityScope;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityException;
use App\Models\Venue;
use App\Models\Workspace;
use App\Services\Availability\AvailabilityScheduleWriter;
use App\Services\Availability\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AvailabilityScheduleWriter $writer,
    ) {}

    public function edit(Venue $venue)
    {
        $this->authorize('update', $venue);
        $venue->load([
            'hours',
            'availabilityExceptions',
            'units:id,venue_id,name,inherits_venue_hours,bookings_paused,bookings_paused_note',
            'units.hours',
            'units.availabilityExceptions',
        ]);

        return Inertia::render(
            request()->routeIs('admin.*') ? 'Admin/Venues/Availability' : 'Owner/Venues/Availability',
            [
                'venue' => $venue,
                'schedule' => $this->availability->weeklySchedule($venue),
                'timezone' => $this->availability->timezoneFor($venue),
                'hours_config_needed' => $this->availability->venueNeedsHoursConfig($venue),
                'units' => $venue->units->map(fn (Workspace $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'inherits_venue_hours' => (bool) $u->inherits_venue_hours,
                    'bookings_paused' => (bool) $u->bookings_paused,
                    'bookings_paused_note' => $u->bookings_paused_note,
                    'schedule' => $this->availability->weeklySchedule($u),
                    'exceptions' => $u->availabilityExceptions,
                ]),
                'exceptions' => $venue->availabilityExceptions,
                'pause' => [
                    'bookings_paused' => (bool) $venue->bookings_paused,
                    'bookings_paused_note' => $venue->bookings_paused_note,
                ],
            ]);
    }

    public function updateHours(Request $request, Venue $venue)
    {
        $this->authorize('update', $venue);
        $data = $request->validate([
            'timezone' => ['nullable', 'timezone'],
            'days' => ['required', 'array', 'size:7'],
            'days.*.weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'days.*.is_closed' => ['required', 'boolean'],
            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
            'acknowledge_conflicts' => ['sometimes', 'boolean'],
        ]);

        $closedWeekdays = collect($data['days'])
            ->filter(fn (array $d) => (bool) ($d['is_closed'] ?? false))
            ->pluck('weekday')
            ->map(fn ($d) => (int) $d)
            ->all();

        if ($closedWeekdays !== [] && ! ($data['acknowledge_conflicts'] ?? false)) {
            $tz = $this->availability->timezoneFor($venue);
            $conflicts = $this->availability->conflictingFutureBookings($venue)
                ->filter(fn ($b) => in_array((int) $b->start_at->copy()->timezone($tz)->dayOfWeek, $closedWeekdays, true));
            if ($conflicts->isNotEmpty()) {
                return $this->conflictsResponse($conflicts);
            }
        }

        if (! empty($data['timezone'])) {
            $venue->update(['timezone' => $data['timezone']]);
        }

        $this->writer->syncVenueHours($venue, $data['days']);

        return back()->with('success', __('Saved.'));
    }

    public function updateUnitHours(Request $request, Venue $venue, Workspace $workspace)
    {
        $this->authorize('update', $venue);
        abort_unless($workspace->venue_id === $venue->id, 404);

        $data = $request->validate([
            'inherits_venue_hours' => ['required', 'boolean'],
            'days' => ['nullable', 'array'],
            'days.*.weekday' => ['required_with:days', 'integer', 'min:0', 'max:6'],
            'days.*.is_closed' => ['required_with:days', 'boolean'],
            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
        ]);

        $this->writer->syncUnitHours(
            $workspace,
            $data['days'] ?? [],
            (bool) $data['inherits_venue_hours'],
        );

        return back()->with('success', __('Saved.'));
    }

    public function updatePause(Request $request, Venue $venue)
    {
        $this->authorize('update', $venue);
        $data = $request->validate([
            'bookings_paused' => ['required', 'boolean'],
            'bookings_paused_note' => ['nullable', 'string', 'max:255'],
            'acknowledge_conflicts' => ['sometimes', 'boolean'],
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
        ]);

        $subject = $venue;
        if (! empty($data['workspace_id'])) {
            $subject = Workspace::query()->where('venue_id', $venue->id)->findOrFail($data['workspace_id']);
        }

        if ($data['bookings_paused']) {
            $conflicts = $this->availability->conflictingFutureBookings($subject);
            if ($conflicts->isNotEmpty() && ! ($data['acknowledge_conflicts'] ?? false)) {
                return $this->conflictsResponse($conflicts);
            }
        }

        $subject->update([
            'bookings_paused' => $data['bookings_paused'],
            'bookings_paused_note' => $data['bookings_paused_note'] ?? null,
        ]);

        return back()->with('success', __('Saved.'));
    }

    public function storeException(Request $request, Venue $venue)
    {
        $this->authorize('update', $venue);
        $data = $this->validatedException($request, $venue);

        if (in_array($data['type'], [AvailabilityExceptionType::Closed->value, AvailabilityExceptionType::SpecialHours->value], true)) {
            $subject = ($data['scope'] === AvailabilityScope::Unit->value)
                ? Workspace::query()->findOrFail($data['workspace_id'])
                : $venue;
            $conflicts = $this->availability->conflictingFutureBookings(
                $subject,
                \Illuminate\Support\Carbon::parse($data['starts_on']),
                \Illuminate\Support\Carbon::parse($data['ends_on']),
            );
            if ($conflicts->isNotEmpty() && ! ($data['acknowledge_conflicts'] ?? false)) {
                return $this->conflictsResponse($conflicts);
            }
        }

        $this->writer->upsertException($data);

        return back()->with('success', __('Saved.'));
    }

    public function destroyException(Venue $venue, AvailabilityException $exception)
    {
        $this->authorize('update', $venue);
        abort_unless(
            ($exception->venue_id === $venue->id)
            || ($exception->workspace && $exception->workspace->venue_id === $venue->id),
            404
        );
        $exception->delete();

        return back()->with('success', __('Deleted.'));
    }

    public function previewConflicts(Request $request, Venue $venue)
    {
        $this->authorize('update', $venue);
        $data = $request->validate([
            'scope' => ['required', Rule::in(AvailabilityScope::values())],
            'workspace_id' => ['nullable', 'integer'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);

        $subject = $venue;
        if ($data['scope'] === AvailabilityScope::Unit->value) {
            $subject = Workspace::query()->where('venue_id', $venue->id)->findOrFail($data['workspace_id']);
        }

        $conflicts = $this->availability->conflictingFutureBookings(
            $subject,
            isset($data['starts_on']) ? \Illuminate\Support\Carbon::parse($data['starts_on']) : null,
            isset($data['ends_on']) ? \Illuminate\Support\Carbon::parse($data['ends_on']) : null,
        );

        return response()->json([
            'conflicts' => $conflicts->map(fn ($b) => [
                'id' => $b->id,
                'user' => $b->user?->name,
                'email' => $b->user?->email,
                'unit' => $b->workspace?->name,
                'start_at' => $b->start_at?->toIso8601String(),
                'end_at' => $b->end_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedException(Request $request, Venue $venue): array
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:availability_exceptions,id'],
            'scope' => ['required', Rule::in(AvailabilityScope::values())],
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'type' => ['required', Rule::in(AvailabilityExceptionType::values())],
            'reason' => ['nullable', 'string', 'max:255'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
            'acknowledge_conflicts' => ['sometimes', 'boolean'],
        ]);

        $data['venue_id'] = $venue->id;
        if ($data['scope'] === AvailabilityScope::Unit->value) {
            abort_unless(
                Workspace::query()->where('venue_id', $venue->id)->whereKey($data['workspace_id'])->exists(),
                404
            );
        }

        return $data;
    }

    private function conflictsResponse(Collection $conflicts)
    {
        return back()
            ->withErrors(['acknowledge_conflicts' => __('availability.conflict_required')])
            ->with('availability_conflicts', $conflicts->map(fn ($b) => [
                'id' => $b->id,
                'user' => $b->user?->name,
                'email' => $b->user?->email,
                'unit' => $b->workspace?->name,
                'start_at' => $b->start_at?->toIso8601String(),
                'end_at' => $b->end_at?->toIso8601String(),
            ])->all());
    }
}

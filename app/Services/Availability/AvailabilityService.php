<?php

namespace App\Services\Availability;

use App\Enums\AvailabilityExceptionType;
use App\Enums\AvailabilityScope;
use App\Enums\BookingStatus;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\UnitHour;
use App\Models\Venue;
use App\Models\VenueHour;
use App\Models\Workspace;
use App\Support\AppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for when a unit may accept new bookings.
 *
 * Evaluation timezone = venues.timezone (default Asia/Gaza). Storage = UTC.
 * Precedence: pause → unit exception → venue exception → unit weekly hours
 * (when inherits_venue_hours=false) → venue weekly hours.
 *
 * v1: a booking must start and end on the same local calendar day and fit
 * entirely inside one open interval (no midnight crossing).
 *
 * Deprecated: workspaces.opening_time / closing_time are no longer read here
 * after the phase-13 backfill; columns remain for rollback only.
 */
class AvailabilityService
{
    public function timezoneFor(Workspace|Venue $subject): string
    {
        if ($subject instanceof Venue) {
            return $subject->timezone ?: AppTimezone::display();
        }

        $subject->loadMissing('venue:id,timezone');

        return $subject->venue?->timezone ?: AppTimezone::display();
    }

    public function parseInput(Workspace $workspace, string|Carbon $value): Carbon
    {
        $tz = $this->timezoneFor($workspace);

        if ($value instanceof Carbon) {
            return $value->copy()->utc()->seconds(0);
        }

        $raw = trim(str_replace('T', ' ', $value));
        $raw = preg_replace('/(Z|[+-]\d{2}:?\d{2})$/', '', $raw) ?? $raw;

        return Carbon::parse(trim($raw), $tz)->utc()->seconds(0);
    }

    public function evaluate(Workspace $workspace, Carbon $startUtc, Carbon $endUtc): AvailabilityDecision
    {
        $workspace->loadMissing(['venue', 'hours', 'venue.hours', 'venue.availabilityExceptions', 'availabilityExceptions']);
        $tz = $this->timezoneFor($workspace);
        $start = $startUtc->copy()->timezone($tz)->seconds(0);
        $end = $endUtc->copy()->timezone($tz)->seconds(0);

        if ($end->lte($start)) {
            return AvailabilityDecision::deny('end_before_start', [], $tz);
        }

        if ($start->toDateString() !== $end->toDateString()) {
            return AvailabilityDecision::deny('no_midnight_crossing', ['tz' => $tz], $tz);
        }

        if ($pause = $this->pauseReason($workspace)) {
            return AvailabilityDecision::deny('paused', ['note' => $pause], $tz);
        }

        $intervals = $this->openIntervalsForLocalDay($workspace, $start->toDateString());
        if ($intervals === []) {
            $exception = $this->exceptionForDate($workspace, $start->toDateString());
            if ($exception?->type === AvailabilityExceptionType::Closed) {
                return AvailabilityDecision::deny('closed_exception', [
                    'from' => $exception->starts_on->toDateString(),
                    'to' => $exception->ends_on->toDateString(),
                    'reason' => $exception->reason ?: __('availability.closed'),
                ], $tz);
            }

            $weekday = (int) $start->dayOfWeek;
            $dayName = $start->locale(app()->getLocale())->dayName;

            return AvailabilityDecision::deny('closed_weekday', [
                'day' => $dayName,
                'weekday' => $weekday,
            ], $tz);
        }

        foreach ($intervals as $interval) {
            if ($start->gte($interval['open']) && $end->lte($interval['close'])) {
                return AvailabilityDecision::ok($intervals, $tz);
            }
        }

        $window = collect($intervals)
            ->map(fn ($i) => $i['open']->format('H:i').'–'.$i['close']->format('H:i'))
            ->implode(', ');

        return AvailabilityDecision::deny('outside_hours', ['window' => $window, 'tz' => $tz], $tz);
    }

    public function assertBookable(Workspace $workspace, Carbon $startUtc, Carbon $endUtc): void
    {
        $decision = $this->evaluate($workspace, $startUtc, $endUtc);
        if ($decision->allowed) {
            return;
        }

        throw ValidationException::withMessages([
            'start_at' => $decision->localizedMessage(),
        ]);
    }

    /**
     * @return list<array{open: Carbon, close: Carbon}>
     */
    public function openIntervalsForLocalDay(Workspace $workspace, string $ymd): array
    {
        $tz = $this->timezoneFor($workspace);
        $exception = $this->exceptionForDate($workspace, $ymd);

        if ($exception) {
            if ($exception->type === AvailabilityExceptionType::Closed) {
                return [];
            }

            if ($exception->type === AvailabilityExceptionType::SpecialHours
                && $exception->opens_at
                && $exception->closes_at) {
                return [[
                    'open' => Carbon::parse($ymd.' '.$this->timeString($exception->opens_at), $tz),
                    'close' => Carbon::parse($ymd.' '.$this->timeString($exception->closes_at), $tz),
                ]];
            }
        }

        $weekday = (int) Carbon::parse($ymd, $tz)->dayOfWeek;
        $row = $this->weeklyRow($workspace, $weekday);
        if ($row !== null) {
            if ($row->is_closed || ! $row->opens_at || ! $row->closes_at) {
                return [];
            }

            return [[
                'open' => Carbon::parse($ymd.' '.$this->timeString($row->opens_at), $tz),
                'close' => Carbon::parse($ymd.' '.$this->timeString($row->closes_at), $tz),
            ]];
        }

        // Pre-backfill / incomplete schedule: temporary safety net only.
        $this->warnDeprecatedHoursFallback($workspace);
        $open = $workspace->opening_time ?: '08:00:00';
        $close = $workspace->closing_time ?: '22:00:00';

        return [[
            'open' => Carbon::parse($ymd.' '.$this->timeString($open), $tz),
            'close' => Carbon::parse($ymd.' '.$this->timeString($close), $tz),
        ]];
    }

    /**
     * True when the venue does not have a complete 7-day venue_hours schedule
     * (owners/admins should see the configuration banner).
     */
    public function venueNeedsHoursConfig(Venue $venue): bool
    {
        return ! app(VenueHoursEnsuring::class)->isComplete($venue);
    }

    /**
     * @var array<int, true>
     */
    private static array $fallbackWarnedVenueIds = [];

    private function warnDeprecatedHoursFallback(Workspace $workspace): void
    {
        $workspace->loadMissing('venue:id,name,slug');
        $venueId = (int) ($workspace->venue_id ?? 0);
        if ($venueId <= 0 || isset(self::$fallbackWarnedVenueIds[$venueId])) {
            return;
        }

        self::$fallbackWarnedVenueIds[$venueId] = true;
        \Illuminate\Support\Facades\Log::warning('availability.deprecated_hours_fallback', [
            'venue_id' => $venueId,
            'venue_slug' => $workspace->venue?->slug,
            'workspace_id' => $workspace->id,
            'message' => 'Venue has incomplete venue_hours; using deprecated opening_time/closing_time as a temporary safety net.',
        ]);
    }

    public function pauseReason(Workspace $workspace): ?string
    {
        $workspace->loadMissing('venue');

        if ($workspace->bookings_paused) {
            return $workspace->bookings_paused_note ?: __('availability.paused_default');
        }

        if ($workspace->venue?->bookings_paused) {
            return $workspace->venue->bookings_paused_note ?: __('availability.paused_default');
        }

        return null;
    }

    /**
     * Badge payload for venue cards / show pages.
     *
     * @return array{status: string, label_key: string, replace: array<string, string>, opens_at: ?string, closes_at: ?string}
     */
    public function openNowBadge(Venue $venue, ?Workspace $unit = null, ?Carbon $atUtc = null): array
    {
        $unit ??= $venue->relationLoaded('units')
            ? $venue->units->first()
            : $venue->units()->orderBy('id')->first();

        if (! $unit) {
            return [
                'status' => 'unknown',
                'label_key' => 'no_units',
                'replace' => [],
                'opens_at' => null,
                'closes_at' => null,
            ];
        }

        $tz = $this->timezoneFor($venue);
        $now = ($atUtc ?? AppTimezone::now())->copy()->timezone($tz);
        $ymd = $now->toDateString();

        if ($this->pauseReason($unit)) {
            return [
                'status' => 'paused',
                'label_key' => 'paused',
                'replace' => ['note' => $this->pauseReason($unit)],
                'opens_at' => null,
                'closes_at' => null,
            ];
        }

        $intervals = $this->openIntervalsForLocalDay($unit, $ymd);
        foreach ($intervals as $interval) {
            if ($now->gte($interval['open']) && $now->lt($interval['close'])) {
                return [
                    'status' => 'open',
                    'label_key' => 'open_now',
                    'replace' => ['until' => $interval['close']->format('H:i')],
                    'opens_at' => $interval['open']->format('H:i'),
                    'closes_at' => $interval['close']->format('H:i'),
                ];
            }
        }

        foreach ($intervals as $interval) {
            if ($now->lt($interval['open'])) {
                return [
                    'status' => 'opens_later',
                    'label_key' => 'opens_at',
                    'replace' => ['time' => $interval['open']->format('H:i')],
                    'opens_at' => $interval['open']->format('H:i'),
                    'closes_at' => $interval['close']->format('H:i'),
                ];
            }
        }

        for ($i = 1; $i <= 7; $i++) {
            $day = $now->copy()->addDays($i);
            $nextIntervals = $this->openIntervalsForLocalDay($unit, $day->toDateString());
            if ($nextIntervals !== []) {
                $first = $nextIntervals[0];

                return [
                    'status' => 'closed',
                    'label_key' => 'opens_next',
                    'replace' => [
                        'day' => $day->locale(app()->getLocale())->isoFormat('ddd'),
                        'time' => $first['open']->format('H:i'),
                    ],
                    'opens_at' => $first['open']->format('H:i'),
                    'closes_at' => $first['close']->format('H:i'),
                ];
            }
        }

        return [
            'status' => 'closed',
            'label_key' => 'closed_today',
            'replace' => [],
            'opens_at' => null,
            'closes_at' => null,
        ];
    }

    /**
     * Search forward for the next open interval that can fit $durationMinutes.
     *
     * @return array{start_at: string, end_at: string, local_start: string, local_end: string, timezone: string}|null
     */
    public function nextAvailableSlot(
        Workspace $workspace,
        int $durationMinutes,
        ?Carbon $fromUtc = null,
        int $searchDays = 14,
    ): ?array {
        $tz = $this->timezoneFor($workspace);
        $cursor = ($fromUtc ?? AppTimezone::now())->copy()->timezone($tz)->seconds(0);
        if ($cursor->second > 0 || $cursor->minute % 15 !== 0) {
            $cursor->addMinutes(15 - ($cursor->minute % 15))->seconds(0);
        }

        for ($d = 0; $d <= $searchDays; $d++) {
            $day = $cursor->copy()->addDays($d)->startOfDay();
            $ymd = $day->toDateString();
            foreach ($this->openIntervalsForLocalDay($workspace, $ymd) as $interval) {
                $start = $d === 0
                    ? ($cursor->gt($interval['open']) ? $cursor->copy() : $interval['open']->copy())
                    : $interval['open']->copy();
                $end = $start->copy()->addMinutes($durationMinutes);
                if ($end->lte($interval['close'])) {
                    $startUtc = $start->copy()->utc();
                    $endUtc = $end->copy()->utc();
                    $decision = $this->evaluate($workspace, $startUtc, $endUtc);
                    if ($decision->allowed) {
                        return [
                            'start_at' => $startUtc->toIso8601String(),
                            'end_at' => $endUtc->toIso8601String(),
                            'local_start' => $start->format('Y-m-d H:i'),
                            'local_end' => $end->format('Y-m-d H:i'),
                            'timezone' => $tz,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Marker used by VenueFilter — actual open-now filtering is applied
     * after eager-load in the public index (intervals need PHP evaluation).
     */
    public function wantsOpenNowFilter(): bool
    {
        return true;
    }

    /**
     * Filter a collection of venues to those open now (post-query, for map/list pages).
     *
     * @param  Collection<int, Venue>  $venues
     * @return Collection<int, Venue>
     */
    public function filterVenuesOpenNow(Collection $venues, ?Carbon $atUtc = null): Collection
    {
        return $venues->filter(function (Venue $venue) use ($atUtc) {
            $unit = $venue->relationLoaded('units')
                ? $venue->units->first(fn (Workspace $u) => ($u->status?->value ?? $u->status) === 'published')
                : null;
            if (! $unit) {
                return false;
            }
            $badge = $this->openNowBadge($venue, $unit, $atUtc);

            return $badge['status'] === 'open';
        })->values();
    }

    /**
     * Future pending/confirmed bookings that would conflict with a new closure/pause/special hours.
     *
     * @return Collection<int, Booking>
     */
    public function conflictingFutureBookings(
        Venue|Workspace $subject,
        ?Carbon $rangeStartLocal = null,
        ?Carbon $rangeEndLocal = null,
    ): Collection {
        $query = Booking::query()
            ->with(['user:id,name,email', 'workspace:id,name,venue_id'])
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->where('start_at', '>', AppTimezone::now());

        if ($subject instanceof Venue) {
            $query->whereHas('workspace', fn (Builder $w) => $w->where('venue_id', $subject->id));
            $tz = $this->timezoneFor($subject);
        } else {
            $query->where('workspace_id', $subject->id);
            $tz = $this->timezoneFor($subject);
        }

        if ($rangeStartLocal && $rangeEndLocal) {
            $startUtc = $rangeStartLocal->copy()->timezone($tz)->startOfDay()->utc();
            $endUtc = $rangeEndLocal->copy()->timezone($tz)->endOfDay()->utc();
            $query->where('start_at', '<=', $endUtc)->where('end_at', '>=', $startUtc);
        }

        return $query->orderBy('start_at')->get();
    }

    /**
     * Weekly schedule payload (7 days) for owner UI / public venue page.
     *
     * @return list<array{weekday: int, opens_at: ?string, closes_at: ?string, is_closed: bool, source: string}>
     */
    public function weeklySchedule(Workspace|Venue $subject): array
    {
        $out = [];
        for ($d = 0; $d <= 6; $d++) {
            if ($subject instanceof Workspace) {
                $row = $this->weeklyRow($subject, $d);
                $source = (! $subject->inherits_venue_hours && $row instanceof UnitHour) ? 'unit' : 'venue';
            } else {
                $row = $subject->relationLoaded('hours')
                    ? $subject->hours->firstWhere('weekday', $d)
                    : VenueHour::query()->where('venue_id', $subject->id)->where('weekday', $d)->first();
                $source = 'venue';
            }

            $out[] = [
                'weekday' => $d,
                'opens_at' => $row?->opens_at ? substr($this->timeString($row->opens_at), 0, 5) : null,
                'closes_at' => $row?->closes_at ? substr($this->timeString($row->closes_at), 0, 5) : null,
                'is_closed' => (bool) ($row?->is_closed ?? true),
                'source' => $source,
            ];
        }

        return $out;
    }

    private function exceptionForDate(Workspace $workspace, string $ymd): ?AvailabilityException
    {
        $workspace->loadMissing(['availabilityExceptions', 'venue.availabilityExceptions']);

        $unitEx = ($workspace->availabilityExceptions ?? collect())
            ->first(fn (AvailabilityException $e) => $e->scope === AvailabilityScope::Unit && $e->coversDate($ymd));
        if ($unitEx) {
            return $unitEx;
        }

        return ($workspace->venue?->availabilityExceptions ?? collect())
            ->first(fn (AvailabilityException $e) => $e->scope === AvailabilityScope::Venue && $e->coversDate($ymd));
    }

    private function weeklyRow(Workspace $workspace, int $weekday): VenueHour|UnitHour|null
    {
        if (! $workspace->inherits_venue_hours) {
            $unitRow = $workspace->relationLoaded('hours')
                ? $workspace->hours->firstWhere('weekday', $weekday)
                : UnitHour::query()->where('workspace_id', $workspace->id)->where('weekday', $weekday)->first();
            if ($unitRow) {
                return $unitRow;
            }
        }

        $workspace->loadMissing('venue.hours');

        return $workspace->venue?->relationLoaded('hours')
            ? $workspace->venue->hours->firstWhere('weekday', $weekday)
            : VenueHour::query()->where('venue_id', $workspace->venue_id)->where('weekday', $weekday)->first();
    }

    private function timeString(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }

        $raw = (string) $value;
        if (preg_match('/^\d{2}:\d{2}$/', $raw)) {
            return $raw.':00';
        }

        return $raw;
    }
}

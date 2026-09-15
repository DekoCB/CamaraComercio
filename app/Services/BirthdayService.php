<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Notification;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * "Cumpleaños de socios": every dated milestone the associates master
 * sheet carries — the legal representative's and the CCH representative's
 * birthdays plus the company's anniversary — surfaced as a calendar page
 * and pushed to the shared notification feed on the day.
 *
 * Notifications are idempotent (one per associate/kind/day, keyed in
 * notifications.entity_id) so the two triggers — the daily scheduler and
 * the per-request fallback in AppServiceProvider — can both fire safely.
 */
class BirthdayService
{
    public const KIND_LEGAL_REP = 'legal_rep';

    public const KIND_CCH_REP = 'cch_rep';

    public const KIND_ANNIVERSARY = 'anniversary';

    /** @var array<string, array{column: string, label: string}> */
    public const KINDS = [
        self::KIND_LEGAL_REP => ['column' => 'legal_rep_birthday', 'label' => 'Representante legal'],
        self::KIND_CCH_REP => ['column' => 'cch_rep_birthday', 'label' => 'Representante ante la CCH'],
        self::KIND_ANNIVERSARY => ['column' => 'anniversary_date', 'label' => 'Aniversario de la empresa'],
    ];

    /**
     * Milestones falling exactly on $date (month/day match, Feb 29 rolls
     * to Feb 28 on non-leap years so nobody is skipped).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function eventsOn(CarbonInterface $date): Collection
    {
        $date = CarbonImmutable::instance($date)->startOfDay();

        return $this->allEvents()
            ->filter(fn (array $e) => $this->nextOccurrence($e['date'], $date)->equalTo($date))
            ->map(fn (array $e) => $this->withOccurrence($e, $date))
            ->values();
    }

    /**
     * Milestones from $from (inclusive) through the following $days days,
     * soonest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function upcoming(CarbonInterface $from, int $days = 30): Collection
    {
        $from = CarbonImmutable::instance($from)->startOfDay();
        $until = $from->addDays($days);

        return $this->allEvents()
            ->map(fn (array $e) => $this->withOccurrence($e, $this->nextOccurrence($e['date'], $from)))
            ->filter(fn (array $e) => $e['occurs_on']->lessThanOrEqualTo($until))
            ->sortBy([['occurs_on', 'asc'], ['person', 'asc']])
            ->values();
    }

    /**
     * Every milestone in a calendar month of a given year, by day.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forMonth(int $year, int $month): Collection
    {
        $first = CarbonImmutable::create($year, $month, 1)->startOfDay();

        return $this->allEvents()
            ->filter(fn (array $e) => (int) $e['date']->month === $month)
            ->map(fn (array $e) => $this->withOccurrence($e, $this->occurrenceInYear($e['date'], $year)))
            ->sortBy([['occurs_on', 'asc'], ['person', 'asc']])
            ->values();
    }

    /**
     * Pushes one notification per milestone happening on $date, skipping
     * those already recorded. Returns how many were created.
     */
    public function notifyFor(CarbonInterface $date): int
    {
        $date = CarbonImmutable::instance($date)->startOfDay();
        $events = $this->eventsOn($date);
        if ($events->isEmpty()) {
            return 0;
        }

        $keys = $events->map(fn (array $e) => $this->dedupeKey($e, $date))->all();
        $already = Notification::query()
            ->where('type', Notification::TYPE_ASSOCIATE_BIRTHDAY)
            ->whereIn('entity_id', $keys)
            ->pluck('entity_id')
            ->all();

        $created = 0;
        foreach ($events as $event) {
            $key = $this->dedupeKey($event, $date);
            if (in_array($key, $already, true)) {
                continue;
            }

            Notification::record(
                Notification::TYPE_ASSOCIATE_BIRTHDAY,
                $this->title($event),
                $this->message($event),
                'associate_birthday',
                $key,
                route('associates.birthdays'),
            );
            $created++;
        }

        return $created;
    }

    /**
     * Per-request fallback for installs without a running scheduler
     * (typical XAMPP): the first authenticated page load of each day
     * triggers today's notifications; a cache flag keeps the check to a
     * single query afterwards. Idempotency in notifyFor() makes a lost
     * cache entry harmless.
     */
    public function ensureNotifiedToday(): void
    {
        $today = CarbonImmutable::today();
        $flag = 'birthdays.notified.'.$today->toDateString();

        if (Cache::has($flag)) {
            return;
        }

        $this->notifyFor($today);
        Cache::put($flag, true, $today->endOfDay());
    }

    /**
     * @return Collection<int, array{associate: Associate, kind: string, kind_label: string, person: string, date: CarbonImmutable}>
     */
    private function allEvents(): Collection
    {
        $associates = Associate::query()
            ->where('status', '!=', Associate::STATUS_DESAFILIADO)
            ->where(function ($q) {
                foreach (self::KINDS as $kind) {
                    $q->orWhereNotNull($kind['column']);
                }
            })
            ->orderBy('name')
            ->get();

        $events = collect();
        foreach ($associates as $associate) {
            foreach (self::KINDS as $kind => $meta) {
                $value = $associate->{$meta['column']};
                if (! $value) {
                    continue;
                }
                $events->push([
                    'associate' => $associate,
                    'kind' => $kind,
                    'kind_label' => $meta['label'],
                    'person' => $this->personFor($associate, $kind),
                    'date' => CarbonImmutable::instance($value)->startOfDay(),
                ]);
            }
        }

        return $events;
    }

    private function personFor(Associate $associate, string $kind): string
    {
        return match ($kind) {
            self::KIND_LEGAL_REP => $associate->legal_rep_name ?: 'Representante legal',
            self::KIND_CCH_REP => $associate->cch_rep_name ?: 'Representante ante la CCH',
            default => $associate->company ?: $associate->name,
        };
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function withOccurrence(array $event, CarbonImmutable $occursOn): array
    {
        $event['occurs_on'] = $occursOn;
        $event['years'] = $occursOn->year - $event['date']->year;
        $event['phone'] = match ($event['kind']) {
            self::KIND_LEGAL_REP => $event['associate']->legal_rep_phone,
            self::KIND_CCH_REP => $event['associate']->cch_rep_phone,
            default => $event['associate']->contact_phone,
        };
        $event['email'] = match ($event['kind']) {
            self::KIND_LEGAL_REP => $event['associate']->legal_rep_email,
            self::KIND_CCH_REP => $event['associate']->cch_rep_email,
            default => $event['associate']->email,
        };

        return $event;
    }

    /** First occurrence of the month/day on or after $from. */
    private function nextOccurrence(CarbonImmutable $date, CarbonImmutable $from): CarbonImmutable
    {
        $candidate = $this->occurrenceInYear($date, $from->year);

        return $candidate->lessThan($from) ? $this->occurrenceInYear($date, $from->year + 1) : $candidate;
    }

    private function occurrenceInYear(CarbonImmutable $date, int $year): CarbonImmutable
    {
        $day = $date->day;
        if ($date->month === 2 && $day === 29 && ! CarbonImmutable::create($year, 1, 1)->isLeapYear()) {
            $day = 28;
        }

        return CarbonImmutable::create($year, $date->month, $day)->startOfDay();
    }

    /** @param  array<string, mixed>  $event */
    private function dedupeKey(array $event, CarbonImmutable $date): string
    {
        return $event['associate']->id.':'.$event['kind'].':'.$date->toDateString();
    }

    /** @param  array<string, mixed>  $event */
    private function title(array $event): string
    {
        return match ($event['kind']) {
            self::KIND_ANNIVERSARY => 'Aniversario: '.$event['person'],
            default => 'Cumpleaños: '.$event['person'],
        };
    }

    /** @param  array<string, mixed>  $event */
    private function message(array $event): string
    {
        $associate = $event['associate']->name;

        return match ($event['kind']) {
            self::KIND_ANNIVERSARY => "Hoy {$associate} cumple {$event['years']} años como empresa.",
            self::KIND_LEGAL_REP => "Hoy cumple {$event['years']} años el representante legal de {$associate}.",
            default => "Hoy cumple {$event['years']} años el representante ante la CCH de {$associate}.",
        };
    }
}

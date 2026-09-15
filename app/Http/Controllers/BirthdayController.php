<?php

namespace App\Http\Controllers;

use App\Services\BirthdayService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BirthdayController extends Controller
{
    public function __construct(private readonly BirthdayService $birthdays) {}

    /**
     * "Cumpleaños de socios": today's milestones, the next 30 days, and a
     * month browser (?month=YYYY-MM) for planning further ahead.
     */
    public function index(Request $request): View
    {
        $today = CarbonImmutable::today();

        $month = $today;
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month', ''))) {
            $month = CarbonImmutable::createFromFormat('Y-m', $request->query('month'))->startOfMonth();
        }

        $upcoming = $this->birthdays->upcoming($today->addDay(), 29);

        return view('associates.birthdays', [
            'today' => $today,
            'todayEvents' => $this->birthdays->eventsOn($today),
            'upcoming' => $upcoming->groupBy(fn (array $e) => $e['occurs_on']->toDateString()),
            'month' => $month,
            'monthEvents' => $this->birthdays->forMonth($month->year, $month->month)
                ->groupBy(fn (array $e) => $e['occurs_on']->toDateString()),
            'prevMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->addMonth()->format('Y-m'),
        ]);
    }
}

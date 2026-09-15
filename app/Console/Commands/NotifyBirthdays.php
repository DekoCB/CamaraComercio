<?php

namespace App\Console\Commands;

use App\Services\BirthdayService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class NotifyBirthdays extends Command
{
    protected $signature = 'birthdays:notify {date? : Día a procesar (YYYY-MM-DD), hoy por defecto}';

    protected $description = 'Publica en el panel de notificaciones los cumpleaños y aniversarios de asociados del día';

    public function handle(BirthdayService $birthdays): int
    {
        $date = $this->argument('date') ? CarbonImmutable::parse($this->argument('date')) : CarbonImmutable::today();

        $created = $birthdays->notifyFor($date);

        $this->info("Cumpleaños del {$date->format('d/m/Y')}: {$created} notificación(es) nueva(s).");

        return self::SUCCESS;
    }
}

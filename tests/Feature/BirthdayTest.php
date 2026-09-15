<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Notification;
use App\Services\BirthdayService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BirthdayTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-15 09:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_page_lists_today_upcoming_and_month_milestones(): void
    {
        Associate::factory()->create([
            'name' => 'Hoy SAC',
            'legal_rep_name' => 'PERSONA DE HOY',
            'legal_rep_birthday' => '1980-09-15',
        ]);
        Associate::factory()->create([
            'name' => 'Pronto SAC',
            'cch_rep_name' => 'PERSONA DE MANANA',
            'cch_rep_birthday' => '1990-09-16',
            'company' => 'Pronto Comercial',
            'anniversary_date' => '2010-10-01',
        ]);
        Associate::factory()->create([
            'name' => 'Lejano SAC',
            'legal_rep_name' => 'PERSONA DE DICIEMBRE',
            'legal_rep_birthday' => '1975-12-24',
        ]);
        Associate::factory()->create([
            'name' => 'Desafiliado SAC',
            'status' => Associate::STATUS_DESAFILIADO,
            'legal_rep_name' => 'PERSONA RETIRADA',
            'legal_rep_birthday' => '1970-09-15',
        ]);

        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get('/associates/birthdays')
            ->assertOk()
            ->assertSee('PERSONA DE HOY')
            ->assertSee('cumple 46 años')
            ->assertSee('PERSONA DE MANANA')
            ->assertSee('Mañana')
            ->assertSee('Pronto Comercial')
            ->assertSee('16 años de la empresa')
            ->assertDontSee('PERSONA DE DICIEMBRE')
            ->assertDontSee('PERSONA RETIRADA');

        $this->actingAs($user)->get('/associates/birthdays?month=2026-12')
            ->assertOk()
            ->assertSee('Diciembre 2026')
            ->assertSee('PERSONA DE DICIEMBRE');
    }

    public function test_command_creates_one_notification_per_milestone_without_duplicates(): void
    {
        $associate = Associate::factory()->create([
            'name' => 'Doble SAC',
            'legal_rep_name' => 'REP LEGAL',
            'legal_rep_birthday' => '1980-09-15',
            'cch_rep_name' => 'REP CCH',
            'cch_rep_birthday' => '1985-09-15',
            'anniversary_date' => '2000-03-03',
        ]);

        $this->artisan('birthdays:notify')->assertSuccessful();
        $this->artisan('birthdays:notify')->assertSuccessful();

        $this->assertSame(2, Notification::where('type', Notification::TYPE_ASSOCIATE_BIRTHDAY)->count());
        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_ASSOCIATE_BIRTHDAY,
            'entity_id' => $associate->id.':legal_rep:2026-09-15',
            'title' => 'Cumpleaños: REP LEGAL',
        ]);
        $this->assertDatabaseHas('notifications', [
            'entity_id' => $associate->id.':cch_rep:2026-09-15',
            'message' => 'Hoy cumple 41 años el representante ante la CCH de Doble SAC.',
        ]);
    }

    public function test_feb_29_birthdays_are_notified_on_feb_28_in_non_leap_years(): void
    {
        CarbonImmutable::setTestNow('2026-02-28 09:00:00');
        Associate::factory()->create(['legal_rep_name' => 'BISIESTO', 'legal_rep_birthday' => '1996-02-29']);

        $this->assertSame(1, app(BirthdayService::class)->notifyFor(CarbonImmutable::today()));
    }

    public function test_first_page_load_of_the_day_publishes_birthday_notifications_automatically(): void
    {
        Cache::flush();
        Associate::factory()->create(['legal_rep_name' => 'AUTOMATICO', 'legal_rep_birthday' => '1980-09-15']);
        $user = $this->userWithPermissions([]);

        $this->assertDatabaseCount('notifications', 0);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Cumpleaños: AUTOMATICO');
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame(1, Notification::where('type', Notification::TYPE_ASSOCIATE_BIRTHDAY)->count());
        $this->assertTrue(Cache::has('birthdays.notified.2026-09-15'));
    }

    public function test_notification_links_to_the_birthdays_page(): void
    {
        Associate::factory()->create(['legal_rep_name' => 'ENLAZADO', 'legal_rep_birthday' => '1980-09-15']);

        app(BirthdayService::class)->notifyFor(CarbonImmutable::today());

        $this->assertDatabaseHas('notifications', ['link' => route('associates.birthdays')]);
    }
}

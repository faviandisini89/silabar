<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Penalty;
use App\Models\ProcurementRecommendation;
use App\Models\UnmetDemandLog;
use App\Models\User;
use App\Services\ItemService;
use App\Services\LoanLifecycle;
use App\Services\LoanService;
use App\Services\ProcurementService;
use App\Services\RequestValidator;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-13 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(Role $role = Role::Peminjam, int $trust = 70): User
    {
        return User::factory()->create(['role' => $role->value, 'trust_score' => $trust, 'is_active' => true]);
    }

    private function item(int $units = 2, bool $approval = true, string $tracking = 'unit', float $value = 1000000): Item
    {
        $cat = Category::firstOrCreate(['slug' => 'umum'], ['name' => 'Umum']);
        $loc = Location::firstOrCreate(['name' => 'Gudang']);

        return app(ItemService::class)->createItem([
            'name' => 'Proyektor Uji', 'sku' => 'PRJ', 'category_id' => $cat->id, 'location_id' => $loc->id, 'tracking_type' => $tracking,
            'max_loan_days' => 3, 'requires_approval' => $approval, 'replacement_value' => $value,
        ], $units);
    }

    private function request(User $u, Item $item, int $qty, ?Carbon $start = null, int $days = 1): Loan
    {
        $start ??= now()->addDay()->setTime(9, 0);

        return app(LoanService::class)->create($u, [$item->id => $qty], $start, $start->copy()->addDays($days), 'Seminar uji');
    }

    public function test_a_request_beyond_stock_is_refused_and_remembered_as_unmet_demand(): void
    {
        $item = $this->item(2);
        $u = $this->user();
        $start = now()->addDay()->setTime(9, 0);
        $v = app(RequestValidator::class);
        $res = $v->validate($u, [$item->id => 3], $start, $start->copy()->addDay());

        $this->assertFalse($res['ok']);
        $this->assertSame('out_of_stock', $res['fails'][0]['code']);
        $this->assertSame(1, $v->recordUnmet($u, $res, $start, $start->copy()->addDay()));
        $log = UnmetDemandLog::first();
        $this->assertSame(3, $log->requested_qty);
        $this->assertSame(2, $log->available_qty);
        $this->assertSame(0, Loan::count());
    }

    public function test_a_fully_booked_window_is_reported_as_date_conflict(): void
    {
        $item = $this->item(1, false);
        $this->request($this->user(), $item, 1); // auto-approved, holds the only unit
        $u = $this->user();
        $start = now()->addDay()->setTime(9, 0);
        $res = app(RequestValidator::class)->validate($u, [$item->id => 1], $start, $start->copy()->addDay());
        $this->assertSame('date_conflict', $res['fails'][0]['code']);
        // the day after is free again, so a suggestion exists
        $this->assertNotNull(app(RequestValidator::class)->suggestStart([$item->id => 1], $start, $start->copy()->addDay()));
    }

    public function test_back_to_back_loans_do_not_block_each_other(): void
    {
        $item = $this->item(1, false);
        $a = now()->addDay()->setTime(9, 0);
        $this->request($this->user(), $item, 1, $a);
        $res = app(RequestValidator::class)->validate($this->user(), [$item->id => 1], $a->copy()->addDay(), $a->copy()->addDays(2));
        $this->assertTrue($res['ok']);
    }

    public function test_flagged_items_wait_for_a_person_unless_the_borrower_is_trusted(): void
    {
        $item = $this->item(3, true);
        $this->assertSame(LoanStatus::Pending, $this->request($this->user(Role::Peminjam, 60), $item, 1)->status);
        $this->assertSame(LoanStatus::Approved, $this->request($this->user(Role::Peminjam, 95), $item, 1)->status);
    }

    public function test_unpaid_penalty_blocks_new_requests(): void
    {
        $item = $this->item(2, false);
        $u = $this->user();
        Penalty::create(['user_id' => $u->id, 'type' => 'late', 'amount' => 50000, 'status' => 'unpaid']);
        $start = now()->addDay()->setTime(9, 0);
        $res = app(RequestValidator::class)->validate($u, [$item->id => 1], $start, $start->copy()->addDay());
        $this->assertSame('outstanding_penalty', $res['fails'][0]['code']);
    }

    public function test_approval_rechecks_stock_and_state_machine_is_enforced(): void
    {
        $item = $this->item(1, true);
        $staff = $this->user(Role::Petugas);
        $loan = $this->request($this->user(Role::Peminjam, 50), $item, 1);
        app(LoanService::class)->approve($loan, $staff);
        $this->assertSame(LoanStatus::Approved, $loan->fresh()->status);
        $this->expectException(DomainException::class);
        app(LoanService::class)->reject($loan->fresh(), $staff, 'terlambat'); // approved -> rejected is illegal
    }

    public function test_handover_requires_matching_available_units_then_late_return_creates_a_penalty(): void
    {
        $item = $this->item(2, true);
        $staff = $this->user(Role::Petugas);
        $svc = app(LoanService::class);
        $loan = $this->request($this->user(Role::Peminjam, 50), $item, 2);
        $svc->approve($loan, $staff);
        $loan = $loan->fresh(['details.item']);
        $d = $loan->details->first();

        try {
            $svc->handover($loan, $staff, [$d->id => ['PRJ-001']], []);
            $this->fail('handover with too few units must fail');
        } catch (DomainException $e) {
            $this->assertStringContainsString('pindai 2 unit', $e->getMessage());
        }

        $svc->handover($loan, $staff, [$d->id => ['PRJ-001', 'prj-002']], []);
        $loan = $loan->fresh(['details']);
        $this->assertSame(LoanStatus::Borrowed, $loan->status);
        $this->assertCount(2, $loan->details, 'one detail row per physical unit');
        $this->assertSame(2, ItemUnit::where('status', 'loaned')->count());

        Carbon::setTestNow($loan->due_at->copy()->addDays(2)->addHour()); // 3 days late (rounded up)
        $rows = $loan->details->mapWithKeys(fn ($x) => [$x->id => ['qty' => 1, 'condition' => 'good']])->all();
        $r = $svc->receiveReturn($loan, $staff, $rows);

        $this->assertTrue($r['done']);
        $this->assertSame(3, $r['late_days']);
        $this->assertSame(LoanStatus::Returned, $loan->fresh()->status);
        $this->assertSame(0, ItemUnit::where('status', 'loaned')->count());
        $p = Penalty::where('type', 'late')->first();
        $this->assertEquals(3 * 2000 * 2, $p->amount);
        $this->assertLessThan(100, $loan->borrower->fresh()->trust_score + 1); // trust score was recalculated
    }

    public function test_handover_refuses_a_unit_that_is_already_out(): void
    {
        $item = $this->item(2, false);
        $staff = $this->user(Role::Petugas);
        $svc = app(LoanService::class);
        $a = $this->request($this->user(), $item, 1, now()->addDay()->setTime(9, 0));
        $b = $this->request($this->user(), $item, 1, now()->addDay()->setTime(9, 0));
        $svc->handover($a->fresh(['details.item']), $staff, [$a->details->first()->id => ['PRJ-001']], []);
        $this->expectException(DomainException::class);
        $svc->handover($b->fresh(['details.item']), $staff, [$b->details->first()->id => ['PRJ-001']], []);
    }

    public function test_damaged_return_sends_the_unit_to_repair_and_bills_a_share_of_its_value(): void
    {
        $item = $this->item(1, false, 'unit', 1000000);
        $staff = $this->user(Role::Petugas);
        $svc = app(LoanService::class);
        $loan = $this->request($this->user(), $item, 1);
        $svc->handover($loan->fresh(['details.item']), $staff, [$loan->details->first()->id => ['PRJ-001']], []);
        $loan = $loan->fresh(['details']);
        $svc->receiveReturn($loan, $staff, [$loan->details->first()->id => ['qty' => 1, 'condition' => 'damaged']]);

        $unit = ItemUnit::first();
        $this->assertSame('maintenance', $unit->status->value);
        $this->assertDatabaseHas('maintenance_logs', ['item_unit_id' => $unit->id, 'status' => 'open']);
        $this->assertEquals(300000, Penalty::where('type', 'damaged')->first()->amount);
        // a unit under repair is no longer lendable
        $start = now()->addDays(2)->setTime(9, 0);
        $this->assertFalse(app(RequestValidator::class)->validate($this->user(), [$item->id => 1], $start, $start->copy()->addDay())['ok']);
    }

    public function test_bulk_items_support_partial_returns(): void
    {
        $item = $this->item(10, false, 'bulk');
        $staff = $this->user(Role::Petugas);
        $svc = app(LoanService::class);
        $loan = $this->request($this->user(), $item, 6);
        $d = $loan->details->first();
        $svc->handover($loan->fresh(['details.item']), $staff, [], [$d->id => true]);
        $r1 = $svc->receiveReturn($loan->fresh(['details']), $staff, [$d->id => ['qty' => 4, 'condition' => 'good']]);
        $this->assertFalse($r1['done']);
        $this->assertSame(LoanStatus::Borrowed, $loan->fresh()->status);
        $r2 = $svc->receiveReturn($loan->fresh(['details']), $staff, [$d->id => ['qty' => 2, 'condition' => 'lost']]);
        $this->assertTrue($r2['done']);
        $this->assertSame(8, $item->fresh()->total_stock, 'lost bulk quantity is written off');
    }

    public function test_the_clock_marks_overdue_reminds_and_cancels_unclaimed_tickets(): void
    {
        $item = $this->item(4, false);
        $staff = $this->user(Role::Petugas);
        $svc = app(LoanService::class);
        $late = $this->request($this->user(), $item, 1, now()->addHour());
        $svc->handover($late->fresh(['details.item']), $staff, [$late->details->first()->id => ['PRJ-001']], []);
        $noShow = $this->request($this->user(), $item, 1, now()->addHours(2));

        Carbon::setTestNow($late->due_at->copy()->subHours(5)); // inside the 24h window
        $this->assertSame(1, app(LoanLifecycle::class)->tick()['reminded']);
        $this->assertSame(0, app(LoanLifecycle::class)->tick()['reminded'], 'reminder is sent once');

        Carbon::setTestNow($late->due_at->copy()->addHours(3));
        $out = app(LoanLifecycle::class)->tick();
        $this->assertSame(1, $out['overdue']);
        $this->assertSame(LoanStatus::Overdue, $late->fresh()->status);
        $this->assertSame(1, $out['cancelled'], 'approved but never collected');
        $this->assertSame(LoanStatus::Cancelled, $noShow->fresh()->status);
    }

    public function test_extension_is_refused_when_someone_else_booked_the_item_afterwards(): void
    {
        $item = $this->item(1, false);
        $staff = $this->user(Role::Petugas);
        $svc = app(LoanService::class);
        $a = $this->request($this->user(), $item, 1, now()->addHour(), 1);
        $svc->handover($a->fresh(['details.item']), $staff, [$a->details->first()->id => ['PRJ-001']], []);
        $this->request($this->user(), $item, 1, $a->due_at->copy()->addHour(), 1); // booked right after
        $ext = $svc->requestExtension($a->fresh(), $a->borrower, 1, 'Acara mundur');
        $this->expectException(DomainException::class);
        $svc->decideExtension($ext, $staff, true);
    }

    public function test_fulfilling_a_recommendation_adds_units_and_starts_monitoring(): void
    {
        $item = $this->item(2, false);
        $admin = $this->user(Role::Admin);
        $rec = ProcurementRecommendation::create([
            'item_id' => $item->id, 'period_start' => now()->subDays(30), 'period_end' => now(), 'unmet_count' => 9, 'affected_users' => 5, 'fill_rate' => 0.6,
            'recommended_add_qty' => 2, 'cause' => 'stock_shortage', 'priority_score' => 0.8, 'status' => 'open', 'generated_at' => now(),
        ]);
        $svc = app(ProcurementService::class);
        try {
            $svc->fulfill($rec, $admin, 2);
            $this->fail('must be approved first');
        } catch (DomainException) {
            $this->assertTrue(true);
        }
        $svc->decide($rec, $admin, \App\Enums\RecStatus::Approved, 'ok');
        $svc->fulfill($rec->fresh(), $admin, 2);
        $this->assertSame(4, $item->fresh()->ownedCount());
        $this->assertEquals(0.6, $rec->fresh()->fill_before);
        $this->assertSame('fulfilled', $rec->fresh()->status->value);
    }
}

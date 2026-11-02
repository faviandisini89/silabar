<?php

namespace Tests\Unit;

use App\Domain\Csv;
use App\Domain\LoanRules;
use App\Domain\PenaltyCalculator;
use App\Domain\RequestChecker;
use App\Domain\Reservations;
use App\Domain\TrustScore;
use App\Enums\LoanStatus;
use PHPUnit\Framework\TestCase;

/** Pure business rules: no framework, no database. Mirrors tools/selftest.php. */
class DomainTest extends TestCase
{
    private const H = 3600;

    private const D = 86400;

    public function test_loan_status_machine_blocks_illegal_jumps(): void
    {
        $this->assertTrue(LoanStatus::Pending->canTransitionTo(LoanStatus::Approved));
        $this->assertFalse(LoanStatus::Pending->canTransitionTo(LoanStatus::Borrowed));
        $this->assertFalse(LoanStatus::Rejected->canTransitionTo(LoanStatus::Borrowed));
        $this->assertSame([LoanStatus::Returned], LoanStatus::Overdue->next());
        $this->assertTrue(LoanStatus::Returned->isTerminal());
    }

    public function test_only_confirmed_loans_reserve_stock(): void
    {
        $this->assertSame(['approved', 'borrowed', 'overdue', 'returned'], LoanStatus::values(LoanStatus::reserving()));
    }

    public function test_reservation_sweep(): void
    {
        $H = self::H;
        $res = [['start' => 0, 'end' => 10 * $H, 'qty' => 2], ['start' => 5 * $H, 'end' => 15 * $H, 'qty' => 3], ['start' => 20 * $H, 'end' => 30 * $H, 'qty' => 4]];
        $this->assertSame(5, Reservations::maxConcurrent($res, 0, 40 * $H));
        $this->assertSame(3, Reservations::maxConcurrent($res, 11 * $H, 19 * $H));
        $back = [['start' => 0, 'end' => 10 * $H, 'qty' => 3], ['start' => 10 * $H, 'end' => 20 * $H, 'qty' => 3]];
        $this->assertSame(3, Reservations::maxConcurrent($back, 0, 20 * $H), 'back-to-back loans must not overlap');
        $this->assertSame('mid', Reservations::loadClass(6, 3));
        $this->assertSame('full', Reservations::loadClass(0, 0));
    }

    public function test_penalties(): void
    {
        $p = new PenaltyCalculator(2000, 30);
        $this->assertSame(12000.0, $p->late(3, 2));
        $this->assertSame(600000.0, $p->damaged(1000000, 2));
        $this->assertSame(750000.0, $p->lost(250000, 3));
        $this->assertSame(2, PenaltyCalculator::lateDays(0, self::D + 1));
        $this->assertSame(0, PenaltyCalculator::lateDays(self::D, 0));
    }

    public function test_trust_score(): void
    {
        $this->assertSame(70, TrustScore::compute([false, false]));
        $this->assertSame(100, TrustScore::compute(array_fill(0, 12, false)));
        $this->assertSame(10, TrustScore::compute(array_fill(0, 12, true)));
        $this->assertSame(92, TrustScore::compute(array_fill(0, 12, false), 2));
    }

    public function test_approval_rule(): void
    {
        $this->assertTrue(LoanRules::needsApproval(true, false, 99, 85));
        $this->assertFalse(LoanRules::needsApproval(true, true, 90, 85));
        $this->assertTrue(LoanRules::needsApproval(true, true, 60, 85));
        $this->assertFalse(LoanRules::needsApproval(false, false, 10, 85));
    }

    public function test_request_checker_reports_each_failure_kind(): void
    {
        $line = fn ($q, $u, $r, $m = 3) => ['item_id' => 1, 'name' => 'Proyektor', 'qty' => $q, 'max_days' => $m, 'usable' => $u, 'reserved' => $r];
        $D = self::D;
        $this->assertTrue(RequestChecker::check([$line(1, 6, 2)], 0, $D, 0)['ok']);
        $this->assertSame('out_of_stock', RequestChecker::check([$line(5, 6, 3)], 0, $D, 0)['fails'][0]['code']);
        $this->assertSame('date_conflict', RequestChecker::check([$line(1, 6, 6)], 0, $D, 0)['fails'][0]['code']);
        $this->assertSame('max_duration_exceeded', RequestChecker::check([$line(1, 6, 0, 3)], 0, 5 * $D, 0)['fails'][0]['code']);
        $this->assertSame('outstanding_penalty', RequestChecker::check([$line(1, 6, 0)], 0, $D, 172000)['fails'][0]['code']);
        $this->assertFalse(RequestChecker::check([$line(1, 6, 0)], $D, 0, 0)['ck']['range']);
    }

    public function test_csv_round_trip(): void
    {
        $rows = [['a,b', 'c"d'], ['1', '2']];
        $this->assertSame($rows, Csv::parse(Csv::build($rows)));
    }
}

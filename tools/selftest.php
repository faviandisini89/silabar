<?php
/**
 * Framework-free self-test of the SILABAR business rules (enums + app/Domain).
 * Run with:  php tools/selftest.php
 */
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/../app/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use App\Domain\{Csv, LoanRules, PenaltyCalculator, RequestChecker, Reservations, TrustScore};
use App\Enums\{LoanStatus, Role};

$pass = 0;
$fail = 0;
function t(string $name, bool $ok): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  ok   ' : '  FAIL ').$name."\n";
}
$H = 3600;
$D = 86400;

echo "State machine\n";
t('pending can be approved/rejected/cancelled', LoanStatus::Pending->canTransitionTo(LoanStatus::Approved) && LoanStatus::Pending->canTransitionTo(LoanStatus::Rejected) && LoanStatus::Pending->canTransitionTo(LoanStatus::Cancelled));
t('pending cannot jump to borrowed', ! LoanStatus::Pending->canTransitionTo(LoanStatus::Borrowed));
t('rejected cannot become borrowed', ! LoanStatus::Rejected->canTransitionTo(LoanStatus::Borrowed));
t('approved -> borrowed / cancelled only', LoanStatus::Approved->canTransitionTo(LoanStatus::Borrowed) && LoanStatus::Approved->canTransitionTo(LoanStatus::Cancelled) && ! LoanStatus::Approved->canTransitionTo(LoanStatus::Returned));
t('borrowed -> overdue / returned', LoanStatus::Borrowed->canTransitionTo(LoanStatus::Overdue) && LoanStatus::Borrowed->canTransitionTo(LoanStatus::Returned));
t('overdue -> returned only', LoanStatus::Overdue->next() === [LoanStatus::Returned]);
t('terminal statuses', LoanStatus::Returned->isTerminal() && LoanStatus::Rejected->isTerminal() && LoanStatus::Cancelled->isTerminal() && ! LoanStatus::Borrowed->isTerminal());
t('only approved/borrowed/overdue/returned reserve stock', LoanStatus::values(LoanStatus::reserving()) === ['approved', 'borrowed', 'overdue', 'returned']);

echo "Roles\n";
t('admin inherits staff rights', Role::Admin->isStaff() && Role::Petugas->isStaff() && ! Role::Peminjam->isStaff());

echo "Reservations sweep\n";
$res = [['start' => 0, 'end' => 10 * $H, 'qty' => 2], ['start' => 5 * $H, 'end' => 15 * $H, 'qty' => 3], ['start' => 20 * $H, 'end' => 30 * $H, 'qty' => 4]];
t('overlapping holds add up', Reservations::maxConcurrent($res, 0, 40 * $H) === 5);
t('window only sees what overlaps it', Reservations::maxConcurrent($res, 11 * $H, 19 * $H) === 3);
t('back-to-back loans do not overlap', Reservations::maxConcurrent([['start' => 0, 'end' => 10 * $H, 'qty' => 3], ['start' => 10 * $H, 'end' => 20 * $H, 'qty' => 3]], 0, 20 * $H) === 3);
t('empty calendar', Reservations::maxConcurrent([], 0, $D) === 0);
t('window clips holds', Reservations::maxConcurrent([['start' => -5 * $H, 'end' => 100 * $H, 'qty' => 2]], 10 * $H, 20 * $H) === 2);
t('calendar colouring', Reservations::loadClass(6, 0) === 'free' && Reservations::loadClass(6, 3) === 'mid' && Reservations::loadClass(6, 6) === 'full' && Reservations::loadClass(0, 0) === 'full');

echo "Penalties\n";
$pc = new PenaltyCalculator(2000, 30);
t('late fee per unit per day', $pc->late(3, 2) === 12000.0 && $pc->late(0, 5) === 0.0);
t('damaged is a share of replacement value', $pc->damaged(1000000, 2) === 600000.0);
t('lost is the full value', $pc->lost(250000, 3) === 750000.0);
t('late days round up', PenaltyCalculator::lateDays(0, $D + 1) === 2 && PenaltyCalculator::lateDays(0, $D) === 1 && PenaltyCalculator::lateDays($D, 0) === 0);

echo "Trust score\n";
t('thin history is neutral', TrustScore::compute([false, false]) === 70);
t('perfect record is 100', TrustScore::compute(array_fill(0, 12, false)) === 100);
t('chronic lateness is low', TrustScore::compute(array_fill(0, 12, true)) === 10);
t('damage lowers the score', TrustScore::compute(array_fill(0, 12, false), 2) === 92);
t('mixed record sits in between', ($s = TrustScore::compute([false, false, true, false, false, false, true, false, false, false, false, false])) > 60 && $s < 100);

echo "Approval rule\n";
t('flagged item needs a human by default', LoanRules::needsApproval(true, false, 99, 85));
t('trusted borrower skips review', ! LoanRules::needsApproval(true, true, 90, 85));
t('untrusted borrower still reviewed', LoanRules::needsApproval(true, true, 60, 85));
t('unflagged items never need review', ! LoanRules::needsApproval(false, false, 10, 85));

echo "Request checker\n";
$line = fn ($q, $u, $r, $m = 3) => ['item_id' => 1, 'name' => 'Proyektor', 'qty' => $q, 'max_days' => $m, 'usable' => $u, 'reserved' => $r];
$ok = RequestChecker::check([$line(1, 6, 2)], 0, $D, 0);
t('valid request passes all four checks', $ok['ok'] && $ok['fails'] === []);
$oos = RequestChecker::check([$line(5, 6, 3)], 0, $D, 0);
t('partly booked -> out_of_stock with remaining qty', ! $oos['ok'] && $oos['fails'][0]['code'] === 'out_of_stock' && $oos['fails'][0]['avail'] === 3 && ! $oos['ck']['stock']);
$dc = RequestChecker::check([$line(1, 6, 6)], 0, $D, 0);
t('fully booked -> date_conflict', $dc['fails'][0]['code'] === 'date_conflict' && ! $dc['ck']['conflict']);
$none = RequestChecker::check([$line(1, 0, 0)], 0, $D, 0);
t('no usable stock at all -> out_of_stock', $none['fails'][0]['code'] === 'out_of_stock');
$long = RequestChecker::check([$line(1, 6, 0, 3)], 0, 5 * $D, 0);
t('too long -> max_duration_exceeded', $long['fails'][0]['code'] === 'max_duration_exceeded' && ! $long['ck']['duration']);
$pen = RequestChecker::check([$line(1, 6, 0)], 0, $D, 172000);
t('unpaid penalty blocks the request', $pen['fails'][0]['code'] === 'outstanding_penalty' && str_contains($pen['fails'][0]['msg'], '172.000'));
$bad = RequestChecker::check([$line(1, 6, 0)], $D, 0, 0);
t('end before start is rejected', ! $bad['ck']['range']);
$multi = RequestChecker::check([$line(1, 6, 6), ['item_id' => 2, 'name' => 'Laptop', 'qty' => 9, 'max_days' => 3, 'usable' => 5, 'reserved' => 0]], 0, $D, 0);
t('every failing line is reported', count($multi['fails']) === 2);

echo "CSV\n";
$rows = Csv::parse("name,sku\n\"Kabel, HDMI\",HDM\n\"Dia berkata \"\"halo\"\"\",X\n\n");
t('quoted commas and quotes', $rows[1][0] === 'Kabel, HDMI' && $rows[2][0] === 'Dia berkata "halo"' && count($rows) === 3);
t('round trip', Csv::parse(Csv::build([['a,b', 'c"d'], ['1', '2']])) === [['a,b', 'c"d'], ['1', '2']]);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

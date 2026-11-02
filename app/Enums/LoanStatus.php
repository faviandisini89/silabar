<?php

namespace App\Enums;

/**
 * Loan lifecycle. Transitions are enforced here so illegal jumps are impossible.
 */
enum LoanStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Borrowed = 'borrowed';
    case Overdue = 'overdue';
    case Returned = 'returned';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Borrowed, self::Cancelled],
            self::Borrowed => [self::Overdue, self::Returned],
            self::Overdue => [self::Returned],
            self::Rejected, self::Cancelled, self::Returned => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    public function isTerminal(): bool
    {
        return $this->next() === [];
    }

    /** Statuses that hold stock for their time window. */
    public static function reserving(): array
    {
        return [self::Approved, self::Borrowed, self::Overdue, self::Returned];
    }

    public static function active(): array
    {
        return [self::Pending, self::Approved, self::Borrowed, self::Overdue];
    }

    /** Statuses that count as a real loan in statistics (items actually handed over). */
    public static function counted(): array
    {
        return [self::Borrowed, self::Overdue, self::Returned];
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Borrowed => 'Dipinjam',
            self::Overdue => 'Terlambat',
            self::Returned => 'Selesai',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'sun',
            self::Approved => 'sky',
            self::Borrowed => 'pri',
            self::Overdue => 'brick',
            self::Returned => 'moss',
            self::Rejected, self::Cancelled => 'slate',
        };
    }

    public static function values(array $cases): array
    {
        return array_map(fn (self $c) => $c->value, $cases);
    }
}

@props(['loan', 'who' => false, 'noSteps' => false])
@php
    $status = $loan->status;
    $names = ['Diajukan', 'Disetujui', 'Dipinjam', 'Selesai'];
    $cur = ['pending' => 0, 'approved' => 1, 'borrowed' => 2, 'overdue' => 2, 'returned' => 3][$status->value] ?? null;
@endphp
<div class="ticket {{ $status->value === 'overdue' ? 'late' : '' }}">
    <div class="tm">
        <div class="row wrap">
            <span class="code">{{ $loan->loan_code }}</span>
            <span class="stamp st-{{ $status->value }}">{{ $status->label() }}</span>
            <span class="sp"></span>
            @if ($who)<span class="xs muted">{{ $loan->borrower->name }}</span>@endif
        </div>
        <div class="its">{{ $loan->itemsSummary() }}</div>
        <div class="when">{{ \App\Support\Fmt::dt($loan->start_at) }} sampai {{ \App\Support\Fmt::dt($loan->due_at) }}<br>{{ $loan->purpose }}</div>
        @unless ($noSteps)
            @if ($cur === null)
                <div class="sm muted">{{ $status->value === 'rejected' ? 'Alasan: '.($loan->reject_reason ?: '-') : ($loan->cancel_note ?: 'Dibatalkan') }}</div>
            @else
                <div class="steps">
                    @foreach ($names as $i => $n)
                        @if ($i)<span class="l {{ $i <= $cur ? 'd' : '' }}"></span>@endif
                        <span class="s {{ $i < $cur || $status->value === 'returned' ? 'd' : '' }} {{ $i === $cur && $status->value !== 'returned' ? 'cur' : '' }}"><i></i>{{ $n }}</span>
                    @endforeach
                </div>
            @endif
        @endunless
        @if (trim((string) $slot) !== '')<div class="row wrap" style="margin-top:2px">{{ $slot }}</div>@endif
    </div>
    <div class="ts">
        <div class="qr" data-qr="{{ $loan->qr_token }}" data-size="72"></div>
        @if (in_array($status->value, ['borrowed', 'overdue']))
            <div class="cd" style="color:{{ $status->value === 'overdue' ? 'var(--brick)' : 'var(--ink)' }}">{{ \App\Support\Fmt::until($loan->due_at) }}</div>
        @elseif ($status->value === 'approved')
            <div class="cd">{{ \App\Support\Fmt::pickup($loan->start_at) }}</div>
        @else
            <div class="cd muted">{{ $status->label() }}</div>
        @endif
    </div>
</div>

@extends('layouts.app')
@section('title', 'Audit log')
@section('subtitle', 'Siapa mengubah apa dan kapan')
@section('content')
<div class="panel">
    <form method="GET" class="ph"><input class="in" name="q" value="{{ $q }}" placeholder="Cari pengguna, kode tiket, atau catatan" style="flex:1;min-width:220px">
        <select class="sel" name="action" style="width:auto" onchange="this.form.submit()"><option value="">Semua aksi</option>@foreach ($actions as $a)<option {{ $action === $a ? 'selected' : '' }}>{{ $a }}</option>@endforeach</select><button class="btn sm">Cari</button></form>
    <div class="tw"><table class="t"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Detail</th></tr></thead><tbody>
        @foreach ($logs as $a)<tr><td class="xs nowrap">{{ $a->created_at->translatedFormat('j M Y, H.i') }}</td><td>{{ $a->causer?->name ?? 'Sistem' }}</td><td><b>{{ $a->action }}</b></td><td class="mono-s">{{ $a->subject_type }}</td><td class="sm muted">{{ $a->properties }}</td></tr>@endforeach
    </tbody></table></div>
    <div class="pager">{{ $logs->links('pagination::simple-default') }}</div>
</div>
@endsection

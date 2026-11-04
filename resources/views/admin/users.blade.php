@extends('layouts.app')
@section('title', 'Pengguna')
@section('subtitle', 'Akun dan peran')
@section('content')
<div class="panel">
    <div class="ph"><form method="GET" class="row" style="flex:1;min-width:240px"><input class="in" name="q" value="{{ $q }}" placeholder="Cari nama atau email"></form>
        <button type="button" class="btn pri" data-open-modal="new-user">{!! \App\Support\Icon::svg('plus', 16) !!}Tambah pengguna</button></div>
    <div class="tw"><table class="t"><thead><tr><th>Nama</th><th>Organisasi</th><th>Peran</th><th>Trust</th><th>Aktif</th></tr></thead><tbody>
        @foreach ($users as $u)
            @php $self = $u->id === auth()->id(); @endphp
            <tr><td><div class="b">{{ $u->name }}</div><div class="xs muted">{{ $u->email }}</div></td><td>{{ $u->organization ?: '-' }}<div class="xs muted">{{ $u->nim }}</div></td>
                <td><form method="POST" action="{{ route('admin.users.update', $u) }}">@csrf @method('PATCH')<select class="sel" style="width:auto" name="role" onchange="this.form.submit()" {{ $self ? 'disabled' : '' }}>@foreach ($roles as $r)<option value="{{ $r->value }}" {{ $u->role === $r ? 'selected' : '' }}>{{ $r->label() }}</option>@endforeach</select></form></td>
                <td>@if ($u->role->value === 'peminjam')<div style="width:90px">{!! \App\Support\Charts::ring($u->trust_score / 100) !!}</div><span class="xs">{{ $u->trust_score }}</span>@else - @endif</td>
                <td><form method="POST" action="{{ route('admin.users.update', $u) }}">@csrf @method('PATCH')<input type="hidden" name="active_form" value="1"><label class="check-row"><input type="checkbox" name="is_active" value="1" {{ $u->is_active ? 'checked' : '' }} {{ $self ? 'disabled' : '' }} onchange="this.form.submit()"> {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</label></form></td></tr>
        @endforeach
    </tbody></table></div>
</div>
<div id="new-user" class="hidden" data-cls="narrow"><div class="mh"><div style="flex:1"><h3>Tambah pengguna</h3></div><button class="ibtn" data-close>{!! \App\Support\Icon::svg('x') !!}</button></div>
    <form method="POST" action="{{ route('admin.users.store') }}">@csrf
        <div class="field"><label>Nama</label><input class="in" name="name" required></div>
        <div class="two"><div class="field"><label>Email</label><input class="in" type="email" name="email" required></div><div class="field"><label>Peran</label><select class="sel" name="role">@foreach ($roles as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach</select></div></div>
        <div class="two"><div class="field"><label>Organisasi atau unit</label><input class="in" name="organization"></div><div class="field"><label>NIM atau NIP</label><input class="in" name="nim"></div></div>
        <p class="hint">Kata sandi default dibuat otomatis oleh sistem. Minta pengguna segera menggantinya setelah masuk.</p>
        <div class="row" style="justify-content:flex-end"><button type="button" class="btn" data-close>Batal</button><button class="btn pri">Simpan</button></div></form></div>
@endsection

@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<div class="auth">
    <!-- Panel Kiri: Hero & Highlight -->
    <section class="show">
        <div class="show-content">
            <div class="brand">{!! \App\Support\Icon::logo(42) !!}<span>SILABAR</span></div>
            
            <div class="hero-text">
                <h1>Pinjam barang kampus tanpa antre buku catatan</h1>
                <p>Cek ketersediaan, ajukan, ambil dengan tiket QR, dan kembalikan tepat waktu. Petugas dan admin melihat stok serta kebutuhan secara langsung.</p>
            </div>

            <div class="availability-board">
                <div class="b-title">Tersedia besok pagi</div>
                <div class="board">
                    @foreach ($board as [$name, $n])
                        <div class="bi">
                            <span>{{ $name }}</span>
                            <b>{{ $n }}</b>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="fan">
                @foreach ($tickets as $i => $t)
                    <div class="tk" style="top:{{ $i * 54 }}px; left:{{ $i * 24 }}px; transform:rotate({{ ($i - 1) * 2 }}deg); z-index:{{ $i + 1 }}">
                        <x-ticket :loan="$t" :who="true" :no-steps="true" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Panel Kanan: Form Login (Tanpa bagian roles) -->
    <section class="form">
        <div class="form-wrapper">
            <!-- Ringkasan Statistik -->
            <div class="strip">
                <div>
                    <b data-count="{{ $stat['running'] }}">0</b>
                    <span>pinjaman berjalan</span>
                </div>
                <div>
                    <b data-count="{{ $stat['units'] }}">0</b>
                    <span>unit siap pakai</span>
                </div>
                <div>
                    <b data-count="{{ $stat['pending'] }}">0</b>
                    <span>menunggu persetujuan</span>
                </div>
            </div>

            <!-- Card Utama Form Login -->
            <div class="auth-card panel">
                <div class="tabs">
                    <a class="tab-item on">Masuk</a>
                    <a href="{{ route('register') }}" class="tab-item">Daftar sebagai peminjam</a>
                </div>

                @include('partials.flash')

                <form method="POST" action="{{ route('login.post') }}" class="auth-form">
                    @csrf
                    
                    <div class="field">
                        <label for="email">Email</label>
                        <input class="in" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" placeholder="nama@kampus.ac.id" required autofocus>
                    </div>

                    <div class="field">
                        <label for="password">Kata sandi</label>
                        <div class="pwd-wrap">
                            <input class="in" id="password" name="password" type="password" autocomplete="current-password" placeholder="••••••••" required>
                            <button type="button" id="togglePassword" class="pwd-btn" aria-label="Tampilkan atau sembunyikan kata sandi">
                                <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                    <path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                    <line x1="2" x2="22" y1="2" y2="22"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="check-row">
                            <input type="checkbox" name="remember" value="1"> 
                            <span>Ingat saya di perangkat ini</span>
                        </label>
                    </div>

                    <button class="btn pri btn-block">Masuk ke Akun</button>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeOpen = document.getElementById('eyeOpen');
    const eyeClosed = document.getElementById('eyeClosed');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            eyeOpen.style.display = isPassword ? 'none' : 'block';
            eyeClosed.style.display = isPassword ? 'block' : 'none';
        });
    }
});
</script>
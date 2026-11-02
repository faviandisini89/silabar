@extends('layouts.guest')
@section('title', 'Daftar')
@section('content')
<div class="auth">
    <!-- Panel Kiri: Hero & Highlight -->
    <section class="show">
        <div class="show-content">
            <div class="brand">{!! \App\Support\Icon::logo(42) !!}<span>SILABAR</span></div>
            
            <div class="hero-text">
                <h1>Satu akun untuk semua peminjamanmu</h1>
                <p>Daftar sebagai peminjam. Peran Petugas dan Admin hanya diberikan oleh Admin.</p>
            </div>

            <div class="availability-board">
                <div class="board" style="grid-template-columns:1fr">
                    <div class="bi"><span>Ajukan beberapa barang sekaligus</span></div>
                    <div class="bi"><span>Tiket QR untuk mengambil dan mengembalikan</span></div>
                    <div class="bi"><span>Pengingat sebelum jatuh tempo</span></div>
                    <div class="bi"><span>Statistik peminjamanmu: harian sampai tahunan</span></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Panel Kanan: Form Daftar -->
    <section class="form">
        <div class="form-wrapper">
            <!-- Card Utama Form Daftar -->
            <div class="auth-card panel">
                <div class="tabs">
                    <a href="{{ route('login') }}" class="tab-item">Masuk</a>
                    <a class="tab-item on">Daftar sebagai peminjam</a>
                </div>

                @include('partials.flash')

                <form method="POST" action="{{ route('register.post') }}" class="auth-form">
                    @csrf
                    
                    <div class="field">
                        <label for="name">Nama lengkap</label>
                        <input class="in" id="name" name="name" value="{{ old('name') }}" placeholder="Nama lengkap Anda" required autofocus>
                    </div>

                    <div class="two">
                        <div class="field">
                            <label for="nim">NIM</label>
                            <input class="in" id="nim" name="nim" value="{{ old('nim') }}" placeholder="Contoh: 235211001">
                        </div>
                        <div class="field">
                            <label for="organization">Organisasi</label>
                            <input class="in" id="organization" name="organization" value="{{ old('organization') }}" placeholder="Contoh: UKM Musik">
                        </div>
                    </div>

                    <div class="field">
                        <label for="email">Email</label>
                        <input class="in" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nama@kampus.ac.id" required>
                    </div>

                    <div class="two">
                        <div class="field">
                            <label for="password">Kata sandi</label>
                            <div class="pwd-wrap">
                                <input class="in" id="password" name="password" type="password" placeholder="••••••••" required>
                                <button type="button" class="pwd-btn toggle-pwd" data-target="password" aria-label="Tampilkan atau sembunyikan kata sandi">
                                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                        <path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                        <line x1="2" x2="22" y1="2" y2="22"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="password_confirmation">Ulangi kata sandi</label>
                            <div class="pwd-wrap">
                                <input class="in" id="password_confirmation" name="password_confirmation" type="password" placeholder="••••••••" required>
                                <button type="button" class="pwd-btn toggle-pwd" data-target="password_confirmation" aria-label="Tampilkan atau sembunyikan kata sandi">
                                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                        <path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                        <line x1="2" x2="22" y1="2" y2="22"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button class="btn pri btn-block" style="margin-top: 10px;">Buat Akun</button>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-pwd').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            const eyeOpen = this.querySelector('.eye-open');
            const eyeClosed = this.querySelector('.eye-closed');

            if (passwordInput) {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                eyeOpen.style.display = isPassword ? 'none' : 'block';
                eyeClosed.style.display = isPassword ? 'block' : 'none';
            }
        });
    });
});
</script>
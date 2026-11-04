@extends('layouts.guest')
@section('title', 'Daftar Peminjam')
@section('content')
<div class="auth-page">
    <div class="auth-container" style="max-width: 520px;">
        <!-- Top Nav / Kembali ke Beranda & Logo -->
        <div class="auth-top">
            <a href="{{ route('root') }}" class="back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Kembali ke Beranda
            </a>
            <div class="brand-badge">
                {!! \App\Support\Icon::logo(28) !!}
                <span>SILABAR</span>
            </div>
        </div>

        <!-- Card Form Register Utama -->
        <div class="auth-card-clean">
            <div class="auth-header">
                <h2>Daftar Akun Peminjam</h2>
                <p>Buat akun baru untuk mulai meminjam barang dan fasilitas kampus secara mandiri.</p>
            </div>

            <div class="tabs">
                <a href="{{ route('login') }}" class="tab-item">Masuk</a>
                <a class="tab-item on">Daftar sebagai peminjam</a>
            </div>

            @include('partials.flash')

            <form method="POST" action="{{ route('register.post') }}" class="auth-form">
                @csrf
                
                <div class="field">
                    <label for="name">Nama Lengkap</label>
                    <input class="in" id="name" name="name" value="{{ old('name') }}" placeholder="Nama lengkap Anda" required autofocus>
                </div>

                <div class="two">
                    <div class="field">
                        <label for="nim">NIM / NIP</label>
                        <input class="in" id="nim" name="nim" value="{{ old('nim') }}" placeholder="Contoh: 235211001">
                    </div>
                    <div class="field">
                        <label for="organization">Organisasi / Prodi</label>
                        <input class="in" id="organization" name="organization" value="{{ old('organization') }}" placeholder="Contoh: Teknik Informatika">
                    </div>
                </div>

                <div class="field">
                    <label for="email">Alamat Email Kampus</label>
                    <input class="in" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nama@kampus.ac.id" required>
                </div>

                <div class="two">
                    <div class="field">
                        <label for="password">Kata Sandi</label>
                        <div class="pwd-wrap">
                            <input class="in" id="password" name="password" type="password" placeholder="Min. 8 karakter" required>
                            <button type="button" class="pwd-btn toggle-pwd" data-target="password" aria-label="Tampilkan kata sandi">
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
                        <label for="password_confirmation">Ulangi Kata Sandi</label>
                        <div class="pwd-wrap">
                            <input class="in" id="password_confirmation" name="password_confirmation" type="password" placeholder="Ulangi sandi" required>
                            <button type="button" class="pwd-btn toggle-pwd" data-target="password_confirmation" aria-label="Tampilkan kata sandi">
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

                <button type="submit" class="btn pri btn-block" style="padding: 12px 20px; font-size: 15px; margin-top: 8px;">
                    Daftar Akun Baru
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-pwd').forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const eyeOpen = this.querySelector('.eye-open');
            const eyeClosed = this.querySelector('.eye-closed');

            if (input) {
                const isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                if (eyeOpen) eyeOpen.style.display = isPassword ? 'none' : 'block';
                if (eyeClosed) eyeClosed.style.display = isPassword ? 'block' : 'none';
            }
        });
    });
});
</script>

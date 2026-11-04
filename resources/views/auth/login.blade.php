@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<div class="auth-page">
    <div class="auth-container">
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

        <!-- Card Form Login Utama -->
        <div class="auth-card-clean">
            <div class="auth-header">
                <h2>Masuk ke Akun</h2>
                <p>Silakan masukkan kredensial akun Anda untuk mengakses sistem peminjaman.</p>
            </div>

            <div class="tabs">
                <a class="tab-item on">Masuk</a>
                <a href="{{ route('register') }}" class="tab-item">Daftar sebagai peminjam</a>
            </div>

            @include('partials.flash')

            <form method="POST" action="{{ route('login.post') }}" class="auth-form">
                @csrf
                
                <div class="field">
                    <label for="email">Alamat Email</label>
                    <input class="in" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" placeholder="nama@kampus.ac.id" required autofocus>
                </div>

                <div class="field">
                    <label for="password">Kata Sandi</label>
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

                <button type="submit" class="btn pri btn-block" style="padding: 12px 20px; font-size: 15px; margin-top: 6px;">
                    Masuk ke Akun
                </button>
            </form>

            @if (!empty($demo) && $demo->isNotEmpty())
                <div style="margin-top: 24px; padding: 14px 16px; background: var(--bg); border-radius: var(--r-md); border: 1px dashed var(--line);">
                    <div style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px;">Akun Uji Coba (Klik untuk mengisi):</div>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        @foreach ($demo as $d)
                            <button type="button" class="btn sm ghost demo-btn" data-email="{{ $d->email }}" style="font-size: 12px; padding: 6px 12px; justify-content: flex-start; text-align: left;">
                                <span style="font-weight: 700; color: var(--teal); min-width: 80px;">{{ $d->role->label() }}:</span>
                                <span>{{ $d->email }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
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

    // Auto-fill akun demo saat diklik
    document.querySelectorAll('.demo-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const email = this.getAttribute('data-email');
            const emailInput = document.getElementById('email');
            const pwdInput = document.getElementById('password');
            if (emailInput && pwdInput) {
                emailInput.value = email;
                pwdInput.value = 'silabar123';
            }
        });
    });
});
</script>

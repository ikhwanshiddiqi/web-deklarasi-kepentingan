@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    @php
        $user = auth()->user();
        $profileComplete = $user?->hasCompleteProfile() ?? false;
        $name = $user?->profile?->full_name ?? ($user?->email ?? 'User');

        // Satu aksi utama, berubah sesuai kondisi
        if (!$profileComplete) {
            $heroTitle = 'Lengkapi profil Anda dulu';
            $heroText = 'Profil harus lengkap sebelum Anda bisa mengisi form deklarasi.';
            $heroLabel = 'Lengkapi Profil';
            $heroRoute = route('account.settings');
        } elseif ($declaration) {
            $heroTitle = 'Deklarasi Anda sudah tersimpan';
            $heroText = 'Terakhir diperbarui ' . $declaration->updated_at?->translatedFormat('d F Y') . '.';
            $heroLabel = 'Lihat Deklarasi';
            $heroRoute = route('declarations.show', $declaration);
        } else {
            $heroTitle = 'Anda belum mengisi deklarasi';
            $heroText = 'Profil sudah lengkap. Silakan isi form deklarasi Anda.';
            $heroLabel = 'Isi Deklarasi Sekarang';
            $heroRoute = route('declarations.create');
        }
    @endphp

    <div class="dashboard-shell">
        <div class="dashboard-header">
            <div>
                <p class="section-label">Portal GRC</p>
                <h1 class="page-title">Selamat datang, {{ $name }}</h1>
            </div>
        </div>

        <div class="panel-card hero-card">
            <div>
                <h2>{{ $heroTitle }}</h2>
                <p>{{ $heroText }}</p>
            </div>
            <a href="{{ $heroRoute }}" class="btn-primary">{{ $heroLabel }}</a>
        </div>

        <div class="content-grid">
            <div class="panel-card">
                <div class="panel-head">
                    <h3>Checklist status</h3>
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Profil</span>
                        @if ($profileComplete)
                            <strong class="check-ok">✓ Lengkap</strong>
                        @else
                            <strong class="check-todo">• Perlu dilengkapi</strong>
                        @endif
                    </div>
                    <div class="summary-row">
                        <span>Form deklarasi</span>
                        @if ($declaration)
                            <strong class="check-ok">✓ Tersimpan</strong>
                        @else
                            <strong class="check-todo">• Belum dibuat</strong>
                        @endif
                    </div>
                </div>
            </div>

            <div class="panel-card">
                <div class="panel-head">
                    <h3>Informasi akun</h3>
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Email</span>
                        <strong>{{ $user?->email }}</strong>
                    </div>
                    <div class="summary-row">
                        <span>Role</span>
                        <strong>{{ $user?->isAdmin() ? 'Admin' : 'Employee' }}</strong>
                    </div>
                    <div class="summary-row">
                        <span>Deklarasi diperbarui</span>
                        <strong>{{ $declaration?->updated_at?->translatedFormat('d M Y') ?? '-' }}</strong>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

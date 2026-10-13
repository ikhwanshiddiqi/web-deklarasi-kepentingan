@extends('layouts.app')

@section('title', 'Beranda Admin')

@section('content')
    @php
        $user = auth()->user();
        $name = $user?->profile?->full_name ?? ($user?->email ?? 'Admin');
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
                <h2>Beranda Admin</h2>
                <p>Kelola data deklarasi dan pengaturan akun dari satu tempat.</p>
            </div>
            <a href="{{ route('admin.data.index') }}" class="btn-primary">Lihat Data</a>
        </div>

        <div class="content-grid">
            <div class="panel-card">
                <div class="panel-head">
                    <h3>Ringkasan cepat</h3>
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Data deklarasi</span>
                        <strong>{{ \App\Models\ConflictDeclaration::count() }}</strong>
                    </div>
                    <div class="summary-row">
                        <span>Total akun</span>
                        <strong>{{ \App\Models\User::count() }}</strong>
                    </div>
                </div>
            </div>

            <div class="panel-card">
                <div class="panel-head">
                    <h3>Akses cepat</h3>
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Data deklarasi</span>
                        <a href="{{ route('admin.data.index') }}"
                            class="text-sm font-semibold text-teal-700 hover:text-teal-800">Buka</a>
                    </div>
                    <div class="summary-row">
                        <span>Kelola akun</span>
                        <a href="{{ route('admin.users.index') }}"
                            class="text-sm font-semibold text-teal-700 hover:text-teal-800">Buka</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Beranda Admin')

@section('content')
    @inject('stats', 'App\\Services\\DeclarationStats')

    @php
        $user = auth()->user();
        $name = $user?->profile?->full_name ?? ($user?->email ?? 'Admin');

        $totalUsers = $stats->totalUsers();
        $filledUsers = min($stats->filledUsers(), $totalUsers);
        $unfilledUsers = $totalUsers - $filledUsers;
        $fmt = fn(int $n): string => number_format($n, 0, ',', '.');

        $pctFilled = $totalUsers > 0 ? (int) round(($filledUsers / $totalUsers) * 100) : 0;
        if ($unfilledUsers > 0) {
            $pctFilled = min($pctFilled, 99);
        }
        if ($filledUsers > 0) {
            $pctFilled = max($pctFilled, 1);
        }
        $pctUnfilled = $totalUsers > 0 ? 100 - $pctFilled : 0;

        $cx = 60;
        $cy = 60;
        $r = 56;
        $point = fn(float $angle): array => [round($cx + $r * cos($angle), 3), round($cy + $r * sin($angle), 3)];
        $sweep = $totalUsers > 0 ? 2 * M_PI * ($filledUsers / $totalUsers) : 0;
        $a0 = -M_PI / 2;
        [$x0, $y0] = $point($a0);
        [$x1, $y1] = $point($a0 + $sweep);

        if ($filledUsers > 0 && $unfilledUsers > 0) {
            $largeFilled = $sweep > M_PI ? 1 : 0;
            $largeUnfilled = $sweep < M_PI ? 1 : 0;
            $shapes = [
                'filled' => "M{$cx} {$cy} L{$x0} {$y0} A{$r} {$r} 0 {$largeFilled} 1 {$x1} {$y1} Z",
                'unfilled' => "M{$cx} {$cy} L{$x1} {$y1} A{$r} {$r} 0 {$largeUnfilled} 1 {$x0} {$y0} Z",
            ];
        } else {
            $full = 'M60 4 A56 56 0 1 1 60 116 A56 56 0 1 1 60 4 Z';
            $shapes = ['filled' => $full, 'unfilled' => $full];
        }

        $slices = [
            ['key' => 'filled', 'label' => 'Sudah mengisi', 'count' => $filledUsers, 'pct' => $pctFilled],
            ['key' => 'unfilled', 'label' => 'Belum mengisi', 'count' => $unfilledUsers, 'pct' => $pctUnfilled],
        ];
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
                        <strong>{{ \App\Models\User::count() - 1 }}</strong>
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

            <div class="panel-card progress-panel" data-pie>
                <div class="panel-head">
                    <h3>Progres pengisian deklarasi</h3>
                    <span class="panel-note">Admin tidak dihitung</span>
                </div>

                @if ($totalUsers > 0)
                    <div class="progress-body">
                        <div class="pie-wrap">
                            <svg viewBox="0 0 120 120" role="group"
                                aria-label="Diagram lingkaran: {{ $pctFilled }}% pengguna sudah mengisi deklarasi">
                                @foreach ($slices as $slice)
                                    @continue($slice['count'] === 0)
                                    <path class="pie-slice pie-slice--{{ $slice['key'] }}" d="{{ $shapes[$slice['key']] }}"
                                        tabindex="0" role="img"
                                        aria-label="{{ $slice['label'] }}: {{ $fmt($slice['count']) }} dari {{ $fmt($totalUsers) }} pengguna ({{ $slice['pct'] }}%)"
                                        data-label="{{ $slice['label'] }}"
                                        data-count="{{ $fmt($slice['count']) }} pengguna"
                                        data-pct="{{ $slice['pct'] }}%" />
                                @endforeach
                            </svg>
                            <div class="pie-tooltip" hidden></div>
                        </div>

                        <div class="progress-side">
                            <p class="hero-figure">{{ $pctFilled }}%</p>
                            <p class="progress-caption">pengguna sudah mengisi deklarasi</p>

                            <div class="summary-box">
                                <div class="summary-row">
                                    <span class="legend-label"><i class="swatch swatch--filled"></i>Sudah mengisi</span>
                                    <strong>{{ $fmt($filledUsers) }} <small>({{ $pctFilled }}%)</small></strong>
                                </div>
                                <div class="summary-row">
                                    <span class="legend-label"><i class="swatch swatch--unfilled"></i>Belum mengisi</span>
                                    <strong>{{ $fmt($unfilledUsers) }} <small>({{ $pctUnfilled }}%)</small></strong>
                                </div>
                                <div class="summary-row">
                                    <span>Total pengguna</span>
                                    <strong>{{ $fmt($totalUsers) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table class="viz-sr-only">
                        <caption>Pengguna yang sudah dan belum mengisi deklarasi (admin tidak dihitung)</caption>
                        <thead>
                            <tr>
                                <th scope="col">Status</th>
                                <th scope="col">Jumlah pengguna</th>
                                <th scope="col">Persentase</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($slices as $slice)
                                <tr>
                                    <th scope="row">{{ $slice['label'] }}</th>
                                    <td>{{ $fmt($slice['count']) }}</td>
                                    <td>{{ $slice['pct'] }}%</td>
                                </tr>
                            @endforeach
                            <tr>
                                <th scope="row">Total pengguna</th>
                                <td>{{ $fmt($totalUsers) }}</td>
                                <td>100%</td>
                            </tr>
                        </tbody>
                    </table>
                @else
                    <p class="progress-empty">Belum ada pengguna yang terdaftar.</p>
                @endif
            </div>
        </div>
    </div>

    @if ($totalUsers > 0)
        <script>
            (function() {
                const root = document.querySelector('[data-pie]');
                if (!root) return;

                const wrap = root.querySelector('.pie-wrap');
                const tip = root.querySelector('.pie-tooltip');

                function show(slice, clientX, clientY) {
                    const value = document.createElement('strong');
                    value.textContent = slice.dataset.count + ' (' + slice.dataset.pct + ')';
                    const label = document.createElement('span');
                    label.textContent = slice.dataset.label;
                    tip.replaceChildren(value, label);
                    tip.hidden = false;

                    const box = wrap.getBoundingClientRect();
                    const x = Math.min(Math.max(clientX - box.left + 14, 0), box.width - tip.offsetWidth);
                    const y = Math.max(clientY - box.top - tip.offsetHeight - 10, -tip.offsetHeight - 6);
                    tip.style.left = x + 'px';
                    tip.style.top = y + 'px';
                }

                function hide() {
                    tip.hidden = true;
                }

                root.querySelectorAll('.pie-slice').forEach(function(slice) {
                    slice.addEventListener('pointermove', function(e) {
                        show(slice, e.clientX, e.clientY);
                    });
                    slice.addEventListener('pointerleave', hide);
                    slice.addEventListener('focus', function() {
                        const r = slice.getBoundingClientRect();
                        show(slice, r.left + r.width / 2, r.top + r.height / 2);
                    });
                    slice.addEventListener('blur', hide);
                });
            })();
        </script>
    @endif
@endsection

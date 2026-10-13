@php
    $categoryMap = $categories ?? config('conflict_categories', []);

    $hasConflict = $declaration->declaration_status === \App\Models\ConflictDeclaration::STATUS_HAS_CONFLICT;

    $checkedKeys = collect($declaration->categories ?? [])
        ->map(fn($c) => is_array($c) ? $c['category_key'] ?? null : $c->category_key)
        ->filter()
        ->values()
        ->all();

    $isChecked = static fn(string $key): bool => in_array($key, $checkedKeys, true);

    $details = collect($declaration->details ?? []);

    $labelFor = static function (string $key) use ($categoryMap): string {
        return $categoryMap[$key]['label'] ?? $key;
    };

    $submittedAt = $declaration->submitted_at;
    if ($submittedAt instanceof \Carbon\CarbonInterface) {
        $tanggal = $submittedAt->copy()->timezone('Asia/Jakarta')->translatedFormat('d F Y');
    } elseif (!empty($submittedAt)) {
        $tanggal = \Illuminate\Support\Carbon::parse($submittedAt)->timezone('Asia/Jakarta')->translatedFormat('d F Y');
    } else {
        $tanggal = now('Asia/Jakarta')->translatedFormat('d F Y');
    }

    $approvedBy = $declaration->employee_name ?: '-';

    $logoPath = public_path('pict/logosp.png');
    $logoExists = is_file($logoPath);
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Pernyataan Potensi Benturan Kepentingan — FI/SP/GRC/973</title>
    <style>
        /* A4, atas 2,5 cm | kanan 2 cm | bawah 2,5 cm | kiri 3 cm (lebih lebar dari DOCX asli: 2,5/1,5) */
        @page {
            size: A4 portrait;
            margin: 2.5cm 2cm 2.5cm 3cm;
        }

        * {
            box-sizing: border-box;
        }

        /* Font dokumen asli: Arial 11 pt (Helvetica bawaan Dompdf = metrik sama dgn Arial) */
        body {
            font-family: Arial, Helvetica, "DejaVu Sans", sans-serif;
            font-size: 11pt;
            line-height: 1.1;
            color: #000;
            margin: 0;
            padding: 0;
        }

        p {
            margin: 0;
        }

        /* ---------- Header (logo + judul) — di badan dokumen, bukan berulang ---------- */
        .doc-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8pt;
        }

        .doc-header td {
            vertical-align: middle;
            padding: 0;
        }

        .logo-cell {
            width: 3.81cm;
            text-align: left;
        }

        /* judul menjorok 2160 twips */
        .logo-cell img {
            width: 1.5cm;
            height: auto;
        }

        .logo-fallback {
            width: 1.5cm;
            height: 1.9cm;
            border: 1px dashed #999;
            color: #999;
            font-size: 7pt;
            text-align: center;
            line-height: 1.9cm;
        }

        .title-cell {
            font-size: 12pt;
            font-weight: bold;
            color: #000;
        }

        /* ---------- Footer tetap tiap halaman (kode dokumen) ---------- */
        .doc-footer {
            position: fixed;
            bottom: -1.25cm;
            /* jarak footer 1,25 cm dari tepi kertas */
            left: 0;
            right: 0;
            height: 1cm;
            text-align: center;
            font-size: 10pt;
            line-height: 1.2;
            color: #000;
        }

        /* ---------- Judul bagian ---------- */
        .section {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            padding-left: .5cm;
            text-indent: -.5cm;
            /* hanging 284 twips */
            margin: 0 0 4pt 0;
        }

        .star {
            color: #ff0000;
        }

        /* ---------- A. Data Karyawan ---------- */
        .data-table {
            border-collapse: collapse;
            margin: 0 0 4pt .8cm;
            width: 15.2cm;
        }

        .data-table td {
            padding: 2pt 4pt;
            vertical-align: top;
            font-size: 11pt;
        }

        .data-table td.label {
            width: 6.19cm;
            font-weight: bold;
            background-color: #f2f2f2;
        }

        .data-table td.sep {
            width: .16cm;
            padding: 2pt 0;
            background-color: #f2f2f2;
        }

        .data-table td.value {
            width: 8.85cm;
            background-color: #bfbfbf;
        }

        /* ---------- B. Deklarasi ---------- */
        .hint {
            font-style: italic;
            font-size: 9.5pt;
            text-align: justify;
            margin: 0 0 4pt 0;
        }

        .intro {
            text-align: justify;
            margin: 0 0 2pt 0;
        }

        .box {
            display: inline-block;
            border: 1px solid #000;
            width: 9pt;
            height: 9pt;
            line-height: 8pt;
            text-align: center;
            font-size: 8pt;
            font-weight: bold;
            margin-right: 3pt;
            vertical-align: middle;
        }

        .cat-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 5pt 0;
        }

        .cat-table td {
            padding: 0 0 2pt 0;
            vertical-align: top;
        }

        .cat-table td.cat-box {
            width: .75cm;
        }

        /* hanging 426 twips */
        .cat-table td.cat-text {
            text-align: justify;
        }

        /* ---------- C. Tabel identifikasi ---------- */
        .c-title {
            margin-top: 0pt;
        }

        .c-hint {
            font-style: italic;
            font-size: 9.5pt;
            text-align: justify;
            margin: 0 0 3pt 0;
        }

        .c-intro {
            text-align: justify;
            margin: 0 0 4pt 0;
        }

        .detail-table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 5pt;
        }

        .detail-table th,
        .detail-table td {
            border: .5pt solid #000;
            padding: 3pt 5pt;
            vertical-align: top;
            font-size: 11pt;
        }

        .detail-table th {
            background-color: #2f5496;
            color: #fff;
            text-align: left;
            font-weight: normal;
            height: 1.1cm;
            padding-top: 4pt;
        }

        .detail-table td.no,
        .detail-table th.no {
            width: 1.34cm;
            text-align: center;
        }

        .detail-table td.cond,
        .detail-table th.cond {
            width: 4.13cm;
        }

        .detail-table td.party,
        .detail-table th.party {
            width: 5.82cm;
        }

        .empty-row td {
            height: .8cm;
        }

        /* ---------- Pernyataan + tanda tangan ---------- */
        .statement {
            text-align: justify;
            margin: 0 0 16pt 0;
        }

        /* Blok "Padang ... Nama" tidak boleh terpisah antar halaman */
        .signoff {
            page-break-inside: avoid;
        }

        .signoff .line,
        .signoff .approved-word,
        .signoff .approved-note,
        .signoff .name-line {
            page-break-inside: avoid;
        }

        .signoff .line {
            margin: 0 0 2pt 0;
        }

        .approved-word {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 1.5pt;
            color: #15803d;
            margin: 2pt 0 1pt 0;
        }

        .approved-note {
            font-size: 7.5pt;
            color: #555;
            font-style: italic;
            line-height: 1.25;
            margin: 0 0 6pt 0;
            width: 8cm;
        }

        .name-line {
            margin-top: 2pt;
        }

        .name-under {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 4.46cm;
        }
    </style>
</head>

<body>

    {{-- Footer: kode dokumen --}}
    <div class="doc-footer">
        FI/SP/GRC/973<br>Rev. 01
    </div>

    {{-- Header: logo + judul (halaman pertama) --}}
    <table class="doc-header">
        <tr>
            <td class="logo-cell">
                @if ($logoExists)
                    <img src="{{ $logoPath }}" alt="PT Semen Padang">
                @else
                    <div class="logo-fallback">LOGO</div>
                @endif
            </td>
            <td class="title-cell">PERNYATAAN POTENSI BENTURAN KEPENTINGAN</td>
        </tr>
    </table>

    {{-- ================= A. DATA KARYAWAN ================= --}}
    <div class="section">
        <div class="section-title">A.&nbsp; Data Karyawan</div>
        <table class="data-table">
            <tr>
                <td class="label">Nama Lengkap</td>
                <td class="sep">:</td>
                <td class="value">{{ $declaration->employee_name ?: '' }}</td>
            </tr>
            <tr>
                <td class="label">Nomor Induk Pegawai (NIP/NIK)</td>
                <td class="sep">:</td>
                <td class="value">{{ $declaration->employee_nik ?: '' }}</td>
            </tr>
            <tr>
                <td class="label">Jabatan / Posisi</td>
                <td class="sep">:</td>
                <td class="value">{{ $declaration->employee_position ?: '' }}</td>
            </tr>
            <tr>
                <td class="label">Unit Kerja</td>
                <td class="sep">:</td>
                <td class="value">{{ $declaration->employee_work_unit ?: '' }}</td>
            </tr>
        </table>
    </div>

    {{-- ================= B. DEKLARASI BENTURAN KEPENTINGAN ================= --}}
    <div class="section">
        <div class="section-title">B.&nbsp; Deklarasi Benturan Kepentingan</div>
        <p class="hint">
            (Beri tanda centang [X] jika Anda memiliki kondisi dan keadaan yang berpotensi
            menimbulkan benturan kepentingan sesuai situasi yang relevan dengan Anda)
        </p>

        {{-- Status: tidak memiliki / memiliki (selalu tampil; yang dipilih ditandai [X]) --}}
        <p class="intro">
            Dengan ini saya menyatakan bahwa saya
            <span class="box">{{ !$hasConflict ? 'X' : '' }}</span><b>tidak memiliki</b>/
            <span class="box">{{ $hasConflict ? 'X' : '' }}</span><b>memiliki</b>
            kondisi yang berpotensi menimbulkan benturan kepentingan sebagai berikut:
        </p>

        {{-- 5 kategori — SEMUA selalu tampil; hanya yang dipilih ditandai [X] --}}
        <table class="cat-table">
            <tr>
                <td class="cat-box"><span class="box">{{ $isChecked('family_relationship') ? 'X' : '' }}</span></td>
                <td class="cat-text">
                    <b>Hubungan keluarga</b>: Memiliki hubungan keluarga dengan Insan Perusahaan lainnya.
                </td>
            </tr>
            <tr>
                <td class="cat-box"><span class="box">{{ $isChecked('multiple_positions') ? 'X' : '' }}</span></td>
                <td class="cat-text">
                    <b>Perangkapan jabatan:</b> Merangkap jabatan pada perusahaan lain yang memiliki
                    hubungan langsung/tidak langsung, sejenis/tidak sejenis, sehingga menyebabkan
                    pemanfaatan suatu jabatan untuk jabatan lainnya yang memiliki hubungan dengan
                    kegiatan bisnis perusahaan
                </td>
            </tr>
            <tr>
                <td class="cat-box"><span class="box">{{ $isChecked('hr_process') ? 'X' : '' }}</span></td>
                <td class="cat-text">
                    <b>Proses SDM:</b> Terlibat dalam pengambilan keputusan terkait rekrutmen,
                    penilaian kinerja, atau promosi pihak yang memiliki hubungan Afiliasi.
                </td>
            </tr>
            <tr>
                <td class="cat-box"><span class="box">{{ $isChecked('procurement_finance') ? 'X' : '' }}</span></td>
                <td class="cat-text">
                    <b>Pengadaan &amp; Keuangan:</b> Terlibat dalam proses seleksi vendor atau
                    persetujuan transaksi keuangan dengan pihak yang memiliki hubungan Afiliasi.
                </td>
            </tr>
            <tr>
                <td class="cat-box"><span class="box">{{ $isChecked('financial_interest') ? 'X' : '' }}</span></td>
                <td class="cat-text">
                    <b>Kepentingan finansial/investasi:</b> Memiliki saham, investasi, atau keuntungan
                    finansial pada Perusahaan pihak ketiga yang menjadi vendor, mitra bisnis, atau
                    pesaing PT Semen Padang.
                </td>
            </tr>
        </table>
    </div>

    {{-- ================= C. TABEL IDENTIFIKASI ================= --}}
    <div class="section">
        <div class="section-title c-title">
            C.&nbsp; Tabel Identifikasi dan Deklarasi Benturan Kepentingan<span class="star">*</span>
        </div>
        <p class="c-hint">
            (<span class="star">*</span>diisi jika sedang/telah terjadi Benturan Kepentingan)
        </p>
        <p class="c-intro">
            Isikan penjelasan atas benturan kepentingan pada kolom berikut jika Bapak/Ibu mencentang
            salah satu poin kondisi pada bagian B di atas:
        </p>

        <table class="detail-table">
            <thead>
                <tr>
                    <th class="no">No.</th>
                    <th class="cond">Kondisi yang dicentang</th>
                    <th class="party">Nama Pihak Terkait/ Perusahaan terafiliasi</th>
                    <th>Penjelasan situasi /Hubungan terkait</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($details as $i => $detail)
                    @php
                        $detailKey = is_array($detail) ? $detail['category_key'] ?? '' : $detail->category_key;
                        $party = is_array($detail) ? $detail['related_party_name'] ?? '' : $detail->related_party_name;
                        $explain = is_array($detail)
                            ? $detail['situation_explanation'] ?? ''
                            : $detail->situation_explanation;
                    @endphp
                    <tr>
                        <td class="no">{{ $i + 1 }}</td>
                        <td class="cond">{{ $labelFor($detailKey) }}</td>
                        <td class="party">{{ $party }}</td>
                        <td>{{ $explain }}</td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td class="no"></td>
                        <td class="cond"></td>
                        <td class="party"></td>
                        <td></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="statement">
        Saya menyatakan bahwa seluruh informasi ini adalah benar dan lengkap sesuai dengan kondisi
        yang saya ketahui saat ini, untuk dapat dipergunakan sebagaimana mestinya.
    </p>

    <div class="signoff">
        <p class="line">Padang, {{ $tanggal }}</p>
        <p class="line">Dibuat oleh,</p>

        {{-- Area tanda tangan diganti penanda sistem APPROVED (hijau). --}}
        <div class="approved-word">APPROVED</div>
        <div class="approved-note">
            Penanda sistem bahwa formulir telah dikirim/dibuat melalui portal. Bukan tanda tangan
            elektronik tersertifikasi.
        </div>

        <p class="name-line"><span class="name-under">{{ $approvedBy }}</span></p>
    </div>

</body>

</html>

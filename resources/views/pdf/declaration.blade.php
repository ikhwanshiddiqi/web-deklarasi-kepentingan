@php
    $categoryMap = $categories ?? config('conflict_categories', []);

    $hasConflict = $declaration->declaration_status === \App\Models\ConflictDeclaration::STATUS_HAS_CONFLICT;

    $checkedKeys = collect($declaration->categories ?? [])
        ->map(fn($c) => is_array($c) ? $c['category_key'] ?? null : $c->category_key)
        ->filter()
        ->values()
        ->all();

    $isChecked = static fn(string $key): bool => in_array($key, $checkedKeys, true);
    $mark = static fn(bool $on): string => $on ? '[&nbsp;X&nbsp;]' : '[&nbsp;&nbsp;&nbsp;]';

    $details = collect($declaration->details ?? []);

    $labelFor = static function (string $key) use ($categoryMap): string {
        return $categoryMap[$key]['label'] ?? $key;
    };

    // Penjelasan: izinkan hanya tag teks dasar, buang semua atribut (style, class, dll).
    // Kalau input aslinya teks biasa dari <textarea>, ganti dengan: nl2br(e($text))
    $cleanHtml = static function (?string $html): string {
        $html = strip_tags((string) $html, '<p><br><b><strong><i><em><u><ul><ol><li>');
        return preg_replace('/<(\w+)\s+[^>]*>/', '<$1>', $html) ?? '';
    };

    // Ubah penjelasan jadi teks biasa lalu pecah per ~140 karakter (di batas kata) agar bisa
    // berpindah halaman di tengah sel.
    $explainChunks = static function (?string $html, int $size = 140): array {
    $text = preg_replace('#<\s*(br|/p|/li)\s*/@endphp#i', "\n", (string) $html);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace("/\n{2,}/", "\n", str_replace("\r", '', $text)));
    if ($text === '') {
        return [''];
    }
    $out = [];
    foreach (explode("\n", $text) as $para) {
        foreach (explode("\n", wordwrap($para, $size, "\n", true)) as $line) {
            $out[] = $line;
        }
    }
    // gabung baris pendek ke chunk ~$size karakter; paragraf baru tetap mulai baris baru
    return $out;
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
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Pernyataan Potensi Benturan Kepentingan — FI/SP/GRC/973</title>
    {{-- Hanya CSS yang didukung mPDF: tanpa @page, position:fixed, display:none, box-sizing, inline-block --}}
    <style>
        body {
            font-family: freesans, sans-serif;
            font-size: 11pt;
            line-height: 1.1;
            color: #000;
        }

        p {
            margin: 0;
        }

        table {
            border-collapse: collapse;
        }

        .doc-header {
            width: 100%;
            margin-bottom: 8pt;
        }

        .doc-header td {
            vertical-align: middle;
            padding: 0;
        }

        .logo-cell {
            width: 3.8cm;
        }

        .title-cell {
            font-size: 12pt;
            font-weight: bold;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 8pt 0 4pt 0;
        }

        .star {
            color: #ff0000;
        }

        .data-table {
            width: 100%;
            margin-bottom: 4pt;
        }

        .data-table td {
            padding: 2pt 4pt;
            vertical-align: top;
        }

        .data-table td.label {
            width: 40%;
            font-weight: bold;
            background-color: #f2f2f2;
        }

        .data-table td.sep {
            width: 1%;
            background-color: #f2f2f2;
        }

        .data-table td.value {
            width: 59%;
            background-color: #e3e3e3;
        }

        .hint,
        .c-hint {
            font-style: italic;
            font-size: 9.5pt;
            text-align: justify;
            margin: 0 0 4pt 0;
        }

        .intro,
        .c-intro {
            text-align: justify;
            margin: 0 0 4pt 0;
        }

        .cat-table {
            width: 100%;
            margin-bottom: 5pt;
        }

        .cat-table td {
            padding: 0 0 3pt 0;
            vertical-align: top;
        }

        .cat-table td.cat-box {
            width: 1.3cm;
            font-family: dejavusansmono, monospace;
            font-size: 10pt;
        }

        .cat-table td.cat-text {
            text-align: justify;
        }

        /* Lebar kolom mengikuti DOCX asli: 8/25/35/32 = 100% (c-expl disesuaikan 31→32) */
        .detail-table {
            width: 100%;
            margin-bottom: 5pt;
            overflow: wrap;
        }

        .detail-table th,
        .detail-table td {
            border: 0.5pt solid #000;
            padding: 3pt 5pt;
            vertical-align: top;
            overflow: wrap;
        }

        .detail-table th {
            background-color: #2f5496;
            color: #fff;
            text-align: left;
            font-weight: normal;
        }

        .detail-table .c-no {
            width: 8%;
            text-align: center;
        }

        .detail-table .c-cond {
            width: 25%;
        }

        .detail-table .c-party {
            width: 35%;
        }

        .detail-table .c-expl {
            width: 32%;
        }

        .detail-table ul,
        .detail-table ol {
            margin: 0;
            padding-left: 14pt;
        }

        /* baris sambungan: tetap bergaris penuh, jadi akhir tiap halaman selalu tertutup garis */
        .detail-table .g-first,
        .detail-table .g-mid,
        .detail-table .g-last {
            padding-top: 1pt;
            padding-bottom: 1pt;
        }

        .statement {
            text-align: justify;
            margin: 6pt 0 14pt 0;
        }

        .signoff {
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
            margin-top: 8pt;
        }

        .name-line {
            margin-top: 8pt;
        }

        .name-under {
            border-bottom: 1px solid #000;
        }
    </style>
</head>

<body>

    {{-- Footer tiap halaman: cara mPDF yang benar (bukan position:fixed) --}}
    <htmlpagefooter name="docfooter">
        <div style="text-align:center; font-size:11pt; line-height:1.1;">FI/SP/GRC/973<br>Rev. 01</div>
    </htmlpagefooter>
    <sethtmlpagefooter name="docfooter" value="on" />

    <table class="doc-header">
        <tr>
            <td class="logo-cell">
                @if ($logoExists)
                    <img src="{{ $logoPath }}" alt="PT Semen Padang" style="width:1.5cm;">
                @endif
            </td>
            <td class="title-cell">PERNYATAAN POTENSI BENTURAN KEPENTINGAN</td>
        </tr>
    </table>

    {{-- A. DATA KARYAWAN --}}
    <div class="section-title">A.&nbsp; Data Karyawan</div>
    <table class="data-table">
        <tr>
            <td class="label">Nama Lengkap</td>
            <td class="sep">:</td>
            <td class="value">{{ $declaration->employee_name }}</td>
        </tr>
        <tr>
            <td class="label">Nomor Induk Pegawai (NIP/NIK)</td>
            <td class="sep">:</td>
            <td class="value">{{ $declaration->employee_nik }}</td>
        </tr>
        <tr>
            <td class="label">Jabatan / Posisi</td>
            <td class="sep">:</td>
            <td class="value">{{ $declaration->employee_position }}</td>
        </tr>
        <tr>
            <td class="label">Unit Kerja</td>
            <td class="sep">:</td>
            <td class="value">{{ $declaration->employee_work_unit }}</td>
        </tr>
    </table>

    {{-- B. DEKLARASI --}}
    <div class="section-title">B.&nbsp; Deklarasi Benturan Kepentingan</div>
    <p class="hint">
        (Beri tanda centang [X] jika Anda memiliki kondisi dan keadaan yang berpotensi
        menimbulkan benturan kepentingan sesuai situasi yang relevan dengan Anda)
    </p>

    <p class="intro">
        Dengan ini saya menyatakan bahwa saya
        <span style="font-family:dejavusansmono; font-size:10pt;">{!! $mark(!$hasConflict) !!}</span> <b>tidak memiliki</b> /
        <span style="font-family:dejavusansmono; font-size:10pt;">{!! $mark($hasConflict) !!}</span> <b>memiliki</b>
        kondisi yang berpotensi menimbulkan benturan kepentingan sebagai berikut:
    </p>

    <table class="cat-table">
        <tr>
            <td class="cat-box">{!! $mark($isChecked('family_relationship')) !!}</td>
            <td class="cat-text"><b>Hubungan keluarga</b>: Memiliki hubungan keluarga dengan Insan Perusahaan lainnya.
            </td>
        </tr>
        <tr>
            <td class="cat-box">{!! $mark($isChecked('multiple_positions')) !!}</td>
            <td class="cat-text"><b>Perangkapan jabatan:</b> Merangkap jabatan pada perusahaan lain yang memiliki
                hubungan langsung/tidak langsung, sejenis/tidak sejenis, sehingga menyebabkan
                pemanfaatan suatu jabatan untuk jabatan lainnya yang memiliki hubungan dengan
                kegiatan bisnis perusahaan</td>
        </tr>
        <tr>
            <td class="cat-box">{!! $mark($isChecked('hr_process')) !!}</td>
            <td class="cat-text"><b>Proses SDM:</b> Terlibat dalam pengambilan keputusan terkait rekrutmen,
                penilaian kinerja, atau promosi pihak yang memiliki hubungan Afiliasi.</td>
        </tr>
        <tr>
            <td class="cat-box">{!! $mark($isChecked('procurement_finance')) !!}</td>
            <td class="cat-text"><b>Pengadaan &amp; Keuangan:</b> Terlibat dalam proses seleksi vendor atau
                persetujuan transaksi keuangan dengan pihak yang memiliki hubungan Afiliasi.</td>
        </tr>
        <tr>
            <td class="cat-box">{!! $mark($isChecked('financial_interest')) !!}</td>
            <td class="cat-text"><b>Kepentingan finansial/investasi:</b> Memiliki saham, investasi, atau keuntungan
                finansial pada Perusahaan pihak ketiga yang menjadi vendor, mitra bisnis, atau
                pesaing PT Semen Padang.</td>
        </tr>
    </table>

    {{-- C. TABEL IDENTIFIKASI --}}
    <div class="section-title">C.&nbsp; Tabel Identifikasi dan Deklarasi Benturan Kepentingan<span
            class="star">*</span></div>
    <p class="c-hint">(<span class="star">*</span>diisi jika sedang/telah terjadi Benturan Kepentingan)</p>
    <p class="c-intro">
        Isikan penjelasan atas benturan kepentingan pada kolom berikut jika Bapak/Ibu mencentang
        salah satu poin kondisi pada bagian B di atas:
    </p>

    <table class="detail-table" repeat_header="1">
        <thead>
            <tr>
                <th class="c-no">No.</th>
                <th class="c-cond">Kondisi yang dicentang</th>
                <th class="c-party">Nama Pihak Terkait / Perusahaan terafiliasi</th>
                <th class="c-expl">Penjelasan situasi / Hubungan terkait</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($details as $index => $detail)
                @php
                    $detailKey = is_array($detail) ? $detail['category_key'] ?? '' : $detail->category_key ?? '';
                    $party = is_array($detail)
                        ? $detail['related_party_name'] ?? ''
                        : $detail->related_party_name ?? '';
                    $explain = is_array($detail)
                        ? $detail['situation_explanation'] ?? ''
                        : $detail->situation_explanation ?? '';
                    $chunks = $explainChunks($explain);
                    $last = count($chunks) - 1;
                @endphp
                {{-- mPDF tidak memotong satu <tr> antar halaman, jadi penjelasan panjang dipecah jadi beberapa baris
                     masing-masing bergaris penuh supaya baris terakhir di tiap halaman tetap tertutup garis. --}}
                @foreach ($chunks as $i => $chunk)
                    @php
                        $pos = $last === 0 ? '' : ($i === 0 ? 'g-first' : ($i === $last ? 'g-last' : 'g-mid'));
                    @endphp
                    <tr>
                        <td class="c-no {{ $pos }}">{!! $i === 0 ? $index + 1 : '&nbsp;' !!}</td>
                        <td class="c-cond {{ $pos }}">{!! $i === 0 ? e($labelFor($detailKey)) : '&nbsp;' !!}</td>
                        <td class="c-party {{ $pos }}">{!! $i === 0 ? e($party) : '&nbsp;' !!}</td>
                        <td class="c-expl {{ $pos }}">{!! e($chunk) !!}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td class="c-no">&nbsp;</td>
                    <td class="c-cond">&nbsp;</td>
                    <td class="c-party">&nbsp;</td>
                    <td class="c-expl">&nbsp;</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="statement">
        Saya menyatakan bahwa seluruh informasi ini adalah benar dan lengkap sesuai dengan kondisi
        yang saya ketahui saat ini, untuk dapat dipergunakan sebagaimana mestinya.
    </p>

    <div class="signoff">
        <p class="line">Padang, {{ $tanggal }}</p>
        <p class="line">Dibuat oleh,</p>
        <div class="approved-word">APPROVED</div>
        <p class="name-line"><span class="name-under">{{ $approvedBy }}</span></p>
    </div>

</body>

</html>

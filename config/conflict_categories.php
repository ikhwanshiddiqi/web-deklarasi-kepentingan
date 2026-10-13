<?php

/*
|--------------------------------------------------------------------------
| Kategori Potensi Benturan Kepentingan
|--------------------------------------------------------------------------
|
| Lima kategori tetap untuk Bagian B formulir FI/SP/GRC/973. Key digunakan
| sebagai nilai di database (conflict_declaration_categories.category_key),
| sedangkan `label` dan `description` (Bahasa Indonesia) dipakai pada form
| dan template PDF. Teks mengacu pada design.md (Bagian B).
|
*/

return [
    'family_relationship' => [
        'label' => 'Hubungan keluarga',
        'description' => 'Memiliki hubungan keluarga dengan Insan Perusahaan lainnya.',
    ],
    'multiple_positions' => [
        'label' => 'Perangkapan jabatan',
        'description' => 'Merangkap jabatan pada perusahaan lain yang memiliki hubungan langsung/tidak langsung atau sejenis/tidak sejenis, sehingga menyebabkan pemanfaatan suatu jabatan untuk jabatan lainnya yang memiliki hubungan dengan kegiatan bisnis perusahaan.',
    ],
    'hr_process' => [
        'label' => 'Proses SDM',
        'description' => 'Terlibat dalam pengambilan keputusan terkait rekrutmen, penilaian kinerja, atau promosi pihak yang memiliki hubungan afiliasi.',
    ],
    'procurement_finance' => [
        'label' => 'Pengadaan & Keuangan',
        'description' => 'Terlibat dalam proses seleksi vendor atau persetujuan transaksi keuangan dengan pihak yang memiliki hubungan afiliasi.',
    ],
    'financial_interest' => [
        'label' => 'Kepentingan finansial/investasi',
        'description' => 'Memiliki saham, investasi, atau keuntungan finansial pada perusahaan pihak ketiga yang menjadi vendor, mitra bisnis, atau pesaing PT Semen Padang.',
    ],
];

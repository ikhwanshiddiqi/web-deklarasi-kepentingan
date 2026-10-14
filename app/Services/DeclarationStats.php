<?php

namespace App\Services;

use App\Models\User;

class DeclarationStats
{
    /**
     * Total pengguna non-admin yang ikut dihitung pada pie chart.
     */
    public function totalUsers(): int
    {
        return User::query()
            ->where('role', '!=', 'admin')
            ->count();
    }

    /**
     * Jumlah pengguna non-admin yang sudah memiliki deklarasi tersimpan.
     */
    public function filledUsers(): int
    {
        return User::query()
            ->where('role', '!=', 'admin')
            ->whereHas('declarations')
            ->count();
    }
}

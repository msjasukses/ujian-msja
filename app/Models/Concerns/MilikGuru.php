<?php

namespace App\Models\Concerns;

use App\Support\Pengguna;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data yang dimiliki seorang guru lewat kolom guru_id (soal, paket soal, ujian).
 *
 * Guru hanya boleh melihat dan mengubah miliknya sendiri; admin/operator
 * melihat semuanya. Pembatasan ini dipasang di route model binding, jadi
 * setiap alamat seperti /soal/{soal}/edit, /ujian/{ujian}/peserta, atau
 * /monitoring/{ujian} otomatis menjawab 404 untuk milik guru lain — tanpa
 * perlu mengingatnya satu per satu di setiap method controller.
 */
trait MilikGuru
{
    public function scopeMilikPengguna(Builder $q): Builder
    {
        return $q->when(Pengguna::guruId(), fn ($w, $guruId) => $w->where($this->qualifyColumn('guru_id'), $guruId));
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->resolveRouteBindingQuery($this->newQuery()->milikPengguna(), $value, $field)->first();
    }
}

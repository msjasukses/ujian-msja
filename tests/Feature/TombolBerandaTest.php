<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Halaman masuk menyediakan jalan pulang ke beranda sekolah. Aplikasi ini
 * sendiri tidak punya halaman depan: akarnya langsung membuka halaman masuk.
 */
class TombolBerandaTest extends TestCase
{
    public function test_akar_aplikasi_diarahkan_ke_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_halaman_login_punya_tombol_beranda(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Beranda')
            // Relatif satu tingkat ke atas, mengikuti letak aplikasi ujian di
            // bawah beranda sekolah — bukan alamat yang ditulis keras.
            ->assertSee('<a class="tombol-beranda" href="../">', false);
    }
}

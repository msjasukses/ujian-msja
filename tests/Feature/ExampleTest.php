<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Akar aplikasi mengarahkan pengunjung ke halaman masuk. */
    public function test_akar_aplikasi_diarahkan_ke_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pagar keselamatan sebelum aplikasi uji dibangun.
     *
     * Kalau konfigurasi Laravel sedang di-cache (sisa `php artisan config:cache` atau `optimize`
     * dari pekerjaan produksi), Laravel memakai konfigurasi hasil cache itu dan MENGABAIKAN seluruh
     * <env> di phpunit.xml. Akibatnya suite ini bisa menunjuk database development, dan test yang
     * memakai RefreshDatabase akan menghapus isinya. Suite berhenti di sini dengan pesan yang jelas
     * daripada menghapus data siapa pun.
     */
    protected function refreshApplication(): void
    {
        $cached = dirname(__DIR__).'/bootstrap/cache/config.php';

        if (is_file($cached)) {
            throw new RuntimeException(
                'Konfigurasi Laravel sedang di-cache ('.$cached.' ada), sehingga setelan database di '
                .'phpunit.xml diabaikan dan test bisa berjalan di database development. '
                .'Jalankan php artisan optimize:clear, lalu ulangi php artisan test.'
            );
        }

        parent::refreshApplication();
    }
}

<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Http\Response;

class PublicSitemap
{
    /**
     * Domain kanonik produksi.
     *
     * Konstanta, bukan config('app.url'): protokol sitemap mensyaratkan URL absolut
     * domain produksi, sedangkan APP_URL berbeda per environment (lokal, testing).
     */
    public const BASE_URL = 'https://penilaianlakmud.mochhuda.id';

    public const NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    /**
     * Halaman publik yang layak diindeks.
     *
     * Daftar ini kosong karena aplikasi belum punya satu pun halaman publik:
     * setiap route panel (dashboard, kegiatan, materi, peserta, absensi, pretest,
     * posttest, keaktifan, etika, pemahaman, perhitungan, hasil, panduan) dijaga
     * middleware auth + permission, sedangkan /login dikecualikan karena halaman
     * autentikasi tidak boleh diindeks. Tambahkan entri hanya untuk halaman yang
     * benar-benar dapat dibuka tanpa login, dan isi lastmod hanya bila tanggal
     * modifikasinya nyata.
     *
     * @return array<int, array{path: string, lastmod: string|null}>
     */
    public static function pages(): array
    {
        return [];
    }

    /**
     * Respons HTTP endpoint /sitemap.xml.
     *
     * 404 selama pages() kosong: sitemap.xsd menetapkan elemen url dengan minOccurs 1,
     * sehingga urlset tanpa url bukan dokumen valid dan dilaporkan "Empty Sitemap" oleh
     * Google Search Console. Endpoint melayani 200 berisi sitemap valid begitu pages()
     * memuat minimal satu halaman publik, dan robots.txt baru boleh mendeklarasikannya
     * pada saat itu.
     */
    public static function response(): Response
    {
        if (self::pages() === []) {
            abort(404, 'Sitemap belum tersedia: belum ada halaman publik yang layak diindeks.');
        }

        return response(self::xml(self::pages()), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * @param  array<int, array{path: string, lastmod: string|null}>  $pages
     */
    public static function xml(array $pages): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $urlset = $document->createElement('urlset');
        $urlset->setAttribute('xmlns', self::NAMESPACE);
        $document->appendChild($urlset);

        foreach ($pages as $page) {
            $url = $document->createElement('url');
            $url->appendChild(self::textNode($document, 'loc', self::BASE_URL.$page['path']));

            if ($page['lastmod'] !== null) {
                $url->appendChild(self::textNode($document, 'lastmod', $page['lastmod']));
            }

            $urlset->appendChild($url);
        }

        return $document->saveXML();
    }

    private static function textNode(DOMDocument $document, string $name, string $value): DOMElement
    {
        $element = $document->createElement($name);
        $element->appendChild($document->createTextNode($value));

        return $element;
    }
}

<?php

namespace Tests\Feature;

use App\Services\PublicSitemap;
use DOMDocument;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    /**
     * Route yang dilarang muncul di sitemap: dashboard, panel internal, dan autentikasi.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_PATHS = [
        '/',
        '/login',
        '/logout',
        '/kegiatan',
        '/materi',
        '/peserta',
        '/absensi',
        '/pretest',
        '/posttest',
        '/keaktifan',
        '/etika',
        '/pemahaman',
        '/perhitungan',
        '/hasil',
        '/panduan',
        '/up',
        '/api',
    ];

    public function test_sitemap_endpoint_serves_nothing_while_no_public_page_is_declared(): void
    {
        $this->assertSame([], PublicSitemap::pages(), 'Daftar halaman publik berubah, perbarui kontrak sitemap.');
        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_xml_builder_emits_a_urlset_with_the_sitemap_protocol_namespace(): void
    {
        $document = $this->parse(PublicSitemap::xml([
            ['path' => '/contoh-publik', 'lastmod' => '2026-10-20'],
        ]));

        $this->assertSame('urlset', $document->documentElement->nodeName);
        $this->assertSame(PublicSitemap::NAMESPACE, $document->documentElement->getAttribute('xmlns'));
        $this->assertSame(1, $document->getElementsByTagName('loc')->length);
    }

    public function test_xml_builder_uses_absolute_production_https_urls(): void
    {
        $document = $this->parse(PublicSitemap::xml([
            ['path' => '/contoh-publik', 'lastmod' => null],
        ]));

        $location = $document->getElementsByTagName('loc')->item(0)->textContent;

        $this->assertStringStartsWith('https://', $location);
        $this->assertSame(PublicSitemap::BASE_URL.'/contoh-publik', $location);
    }

    public function test_xml_builder_writes_lastmod_only_when_a_real_date_is_supplied(): void
    {
        $withDate = $this->parse(PublicSitemap::xml([['path' => '/a', 'lastmod' => '2026-10-20']]));
        $withoutDate = $this->parse(PublicSitemap::xml([['path' => '/b', 'lastmod' => null]]));

        $this->assertSame('2026-10-20', $withDate->getElementsByTagName('lastmod')->item(0)->textContent);
        $this->assertSame(0, $withoutDate->getElementsByTagName('lastmod')->length);
    }

    public function test_declared_public_pages_are_unique_and_never_internal_or_authentication_related(): void
    {
        $locations = $this->locations(PublicSitemap::xml(PublicSitemap::pages()));

        $this->assertSame($locations, array_values(array_unique($locations)), 'Sitemap memuat URL duplikat.');

        foreach ($locations as $location) {
            $this->assertStringStartsWith(PublicSitemap::BASE_URL.'/', $location);
            $this->assertNotContains(
                parse_url($location, PHP_URL_PATH),
                self::FORBIDDEN_PATHS,
                'Sitemap memuat URL internal atau sensitif: '.$location
            );
        }
    }

    public function test_panel_routes_stay_behind_authentication_so_they_are_not_indexable(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/kegiatan')->assertRedirect('/login');
    }

    public function test_robots_txt_declares_a_sitemap_only_while_the_endpoint_serves_one(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('User-agent: *', $robots);
        $this->assertStringContainsString('Disallow:', $robots);
        $this->assertSame(
            PublicSitemap::pages() === [] ? 0 : 1,
            substr_count($robots, 'Sitemap:'),
            'robots.txt hanya boleh mendeklarasikan sitemap saat endpoint benar-benar melayaninya.'
        );

        if (PublicSitemap::pages() !== []) {
            $this->assertStringContainsString('Sitemap: '.PublicSitemap::BASE_URL.'/sitemap.xml', $robots);
        }
    }

    private function parse(string $xml): DOMDocument
    {
        $document = new DOMDocument;

        $this->assertTrue($document->loadXML($xml), 'Sitemap tidak dapat diparsing sebagai XML.');

        return $document;
    }

    /**
     * @return array<int, string>
     */
    private function locations(string $xml): array
    {
        $locations = [];

        foreach ($this->parse($xml)->getElementsByTagName('loc') as $loc) {
            $locations[] = $loc->textContent;
        }

        return $locations;
    }
}

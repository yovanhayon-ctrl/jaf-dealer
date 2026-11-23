<?php

namespace Tests\Feature\Public;

use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SparePartCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_dan_customer_bisa_mengakses_katalog_spare_part(): void
    {
        $part = SparePart::factory()->create([
            'name' => 'Kampas Rem Depan Livina',
            'part_number' => 'D1060-1KA0A',
            'price' => 450_000,
            'is_active' => true,
        ]);

        // Tamu (guest) bisa melihat
        $this->get(route('spare-parts.index'))
            ->assertOk()
            ->assertSee('Kampas Rem Depan Livina')
            ->assertSee('D1060-1KA0A')
            ->assertSee('Rp 450.000')
            ->assertSee('class="nav-link active" href="'.route('spare-parts.index').'"', false);

        // Customer login juga bisa melihat
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('spare-parts.index'))
            ->assertOk()
            ->assertSee('Kampas Rem Depan Livina');
    }

    public function test_spare_part_nonaktif_tidak_muncul_di_katalog_publik(): void
    {
        SparePart::factory()->create([
            'name' => 'Part Aktif Tersedia',
            'is_active' => true,
        ]);

        SparePart::factory()->inactive()->create([
            'name' => 'Part Rahasia Nonaktif',
        ]);

        $this->get(route('spare-parts.index'))
            ->assertOk()
            ->assertSee('Part Aktif Tersedia')
            ->assertDontSee('Part Rahasia Nonaktif');
    }

    public function test_pencarian_dan_filter_kategori_spare_part_publik(): void
    {
        SparePart::factory()->create([
            'name' => 'Filter Oli Mesin',
            'part_number' => '15208-65F0A',
            'category' => 'mesin',
            'compatibility' => 'Nissan Grand Livina',
            'is_active' => true,
        ]);

        SparePart::factory()->create([
            'name' => 'Busi Iridium',
            'part_number' => 'PLZKAR6A',
            'category' => 'kelistrikan',
            'compatibility' => 'Toyota Avanza',
            'is_active' => true,
        ]);

        // Cari berdasarkan nama
        $this->get(route('spare-parts.index', ['q' => 'Filter']))
            ->assertOk()
            ->assertSee('Filter Oli Mesin')
            ->assertDontSee('Busi Iridium');

        // Cari berdasarkan model mobil kompatibel
        $this->get(route('spare-parts.index', ['q' => 'Avanza']))
            ->assertOk()
            ->assertSee('Busi Iridium')
            ->assertDontSee('Filter Oli Mesin');

        // Filter berdasarkan kategori
        $this->get(route('spare-parts.index', ['kategori' => 'kelistrikan']))
            ->assertOk()
            ->assertSee('Busi Iridium')
            ->assertDontSee('Filter Oli Mesin');
    }

    public function test_filter_hanya_stok_tersedia(): void
    {
        SparePart::factory()->create([
            'name' => 'Oli Mesin Ada',
            'stock' => 10,
            'is_active' => true,
        ]);

        SparePart::factory()->create([
            'name' => 'Aki Habis',
            'stock' => 0,
            'is_active' => true,
        ]);

        $this->get(route('spare-parts.index', ['tersedia' => '1']))
            ->assertOk()
            ->assertSee('Oli Mesin Ada')
            ->assertDontSee('Aki Habis');
    }

    public function test_halaman_detail_spare_part_menampilkan_data_dan_tombol_pesan(): void
    {
        config(['dealer.whatsapp' => '6281234567890']);

        $part = SparePart::factory()->create([
            'name' => 'Shockbreaker Gas',
            'slug' => 'shockbreaker-gas',
            'part_number' => '54302-1KA0A',
            'category' => 'suspensi',
            'compatibility' => 'Nissan Grand Livina L10',
            'price' => 850_000,
            'unit' => 'pcs',
            'stock' => 5,
            'description' => 'Peredam kejut gas berkualitas prima.',
            'is_active' => true,
        ]);

        $this->get(route('spare-parts.show', $part))
            ->assertOk()
            ->assertSee('Shockbreaker Gas')
            ->assertSee('54302-1KA0A')
            ->assertSee('Rp 850.000')
            ->assertSee('/ pcs')
            ->assertDontSee('Harga satuan / pcs')
            ->assertSee('Stok: <span class="fw-semibold">5 pcs</span>', false)
            ->assertSee('Nissan Grand Livina L10')
            ->assertSee('Peredam kejut gas berkualitas prima.')
            ->assertSee('Pesan Sekarang via WhatsApp')
            ->assertSee('https://wa.me/6281234567890', false);
    }

    public function test_detail_spare_part_nonaktif_mengembalikan_404(): void
    {
        $inactive = SparePart::factory()->inactive()->create([
            'name' => 'Part Nonaktif',
            'slug' => 'part-nonaktif',
        ]);

        $this->get(route('spare-parts.show', $inactive))
            ->assertNotFound();
    }

    public function test_katalog_dan_detail_menampilkan_gambar_dari_link_eksternal(): void
    {
        $externalUrl = 'https://images.example.com/parts/filter-oli-genuine.png';

        $part = SparePart::factory()->create([
            'name' => 'Filter Oli Genuine Eksternal',
            'slug' => 'filter-oli-genuine-eksternal',
            'part_number' => 'EXT-12345',
            'image' => $externalUrl,
            'is_active' => true,
        ]);

        // Di katalog publik
        $this->get(route('spare-parts.index'))
            ->assertOk()
            ->assertSee($externalUrl, false);

        // Di halaman detail
        $this->get(route('spare-parts.show', $part))
            ->assertOk()
            ->assertSee($externalUrl, false);
    }

    public function test_filter_model_mobil_kompatibilitas(): void
    {
        SparePart::factory()->create([
            'name' => 'Kampas Rem Livina',
            'compatibility' => 'Nissan Grand Livina L10/L11',
            'is_active' => true,
        ]);

        SparePart::factory()->create([
            'name' => 'Filter Oli Universal',
            'compatibility' => 'Universal (Semua Mobil)',
            'is_active' => true,
        ]);

        SparePart::factory()->create([
            'name' => 'Wiper Serena',
            'compatibility' => 'Nissan Serena C26',
            'is_active' => true,
        ]);

        // Filter Livina
        $this->get(route('spare-parts.index', ['model' => 'livina']))
            ->assertOk()
            ->assertSee('Kampas Rem Livina')
            ->assertDontSee('Wiper Serena');

        // Filter Serena
        $this->get(route('spare-parts.index', ['model' => 'serena']))
            ->assertOk()
            ->assertSee('Wiper Serena')
            ->assertDontSee('Kampas Rem Livina');
    }

    public function test_detail_spare_part_memiliki_quantity_selector_dan_link_booking_servis(): void
    {
        $part = SparePart::factory()->create([
            'name' => 'Busi Laser Iridium',
            'part_number' => 'PLZKAR6A-11',
            'price' => 135_000,
            'unit' => 'pcs',
            'stock' => 10,
            'compatibility' => 'Nissan Grand Livina',
            'is_active' => true,
        ]);

        $this->get(route('spare-parts.show', $part))
            ->assertOk()
            ->assertSee('id="partQtyInput"', false)
            ->assertSee('id="partSubtotalDisplay"', false)
            ->assertSee('Deskripsi &amp; Spesifikasi Teknis', false)
            ->assertSee('Nissan Genuine / OEM')
            ->assertSee('part_id='.$part->id)
            ->assertSee('qty=1');
    }

    public function test_booking_servis_dengan_parameter_part_id_dan_qty_mengisi_keluhan_dan_layanan(): void
    {
        $customer = User::factory()->create();

        $part = SparePart::factory()->create([
            'name' => 'Filter Oli Mesin Genuine',
            'part_number' => '15208-65F0A',
            'price' => 85_000,
            'unit' => 'pcs',
            'stock' => 10,
            'is_active' => true,
        ]);

        $service = \App\Models\Service::factory()->create([
            'name' => 'Suku Cadang',
            'slug' => 'suku-cadang',
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer)->get(route('service-bookings.create', [
            'part_id' => $part->id,
            'qty' => 2,
        ]));

        $response->assertOk()
            ->assertSee('Pemasangan Suku Cadang: Filter Oli Mesin Genuine (SKU: 15208-65F0A) sebanyak 2 pcs — Estimasi Part: Rp 170.000.')
            ->assertSee('Suku Cadang Dipesan')
            ->assertSee('Rp 170.000')
            ->assertSee('value="'.$service->id.'" selected', false);
    }
}

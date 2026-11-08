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
}

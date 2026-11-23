<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Car;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarLegalAndWarrantyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Brand $brand;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->brand = Brand::factory()->create(['name' => 'Nissan']);
        $this->category = Category::factory()->create(['name' => 'Sport']);
    }

    public function test_admin_dapat_menyimpan_dan_mengubah_data_legalitas_serta_garansi_mobil(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cars.store'), [
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => '350Z (Z33)',
            'vehicle_condition' => Car::CONDITION_USED,
            'mileage' => 98_000,
            'year' => 2007,
            'price' => 480_000_000,
            'transmission' => 'manual',
            'fuel_type' => 'bensin',
            'engine_cc' => 3498,
            'seats' => 2,
            'color' => 'Silver',
            'stock' => 1,
            'description' => 'Fairlady Z generasi Z33.',
            'tax_status' => 'Pajak Hidup (s/d 10/2027)',
            'document_status' => 'Lengkap (BPKB, STNK, Faktur Asli)',
            'ownership_history' => 'Tangan Pertama dari Baru',
            'plate_type' => 'Ganjil',
            'warranty_info' => 'Garansi Mesin & Transmisi 1 Tahun, Lulus Inspeksi 150 Titik',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.cars.index'));

        $car = Car::where('name', '350Z (Z33)')->firstOrFail();
        $this->assertSame('Pajak Hidup (s/d 10/2027)', $car->tax_status);
        $this->assertSame('Lengkap (BPKB, STNK, Faktur Asli)', $car->document_status);
        $this->assertSame('Tangan Pertama dari Baru', $car->ownership_history);
        $this->assertSame('Ganjil', $car->plate_type);
        $this->assertSame('Garansi Mesin & Transmisi 1 Tahun, Lulus Inspeksi 150 Titik', $car->warranty_info);
        $this->assertTrue($car->isUsed());
    }

    public function test_detail_mobil_menampilkan_spesifikasi_legalitas_dan_lencana_jaminan(): void
    {
        $car = Car::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => '350Z (Z33)',
            'vehicle_condition' => Car::CONDITION_USED,
            'mileage' => 98_000,
            'year' => 2007,
            'price' => 480_000_000,
            'stock' => 1,
            'is_active' => true,
            'tax_status' => 'Pajak Hidup (s/d 10/2027)',
            'document_status' => 'Lengkap (BPKB, STNK, Faktur Asli)',
            'ownership_history' => 'Tangan Pertama dari Baru',
            'plate_type' => 'Ganjil',
            'warranty_info' => 'Garansi Mesin & Transmisi 1 Tahun',
        ]);

        $response = $this->get(route('cars.show', $car));

        $response->assertOk()
            ->assertSee('Status Pajak')
            ->assertSee('Pajak Hidup (s/d 10/2027)')
            ->assertSee('Dokumen')
            ->assertSee('Lengkap (BPKB, STNK, Faktur Asli)')
            ->assertSee('Kepemilikan')
            ->assertSee('Tangan Pertama dari Baru')
            ->assertSee('Plat Nomor')
            ->assertSee('Plat Ganjil')
            ->assertSee('Jaminan Resmi JAF Dealer')
            ->assertSee('Inspeksi 150+ Titik')
            ->assertSee('Bebas Banjir &amp; Bebas Tabrakan', false)
            ->assertSee('Garansi Mesin &amp; Transmisi 1 Tahun', false);
    }
}

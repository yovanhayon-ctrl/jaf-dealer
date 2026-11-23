<?php

namespace Tests\Feature;

use App\Actions\ChangePurchaseRequestStatus;
use App\Models\Brand;
use App\Models\Car;
use App\Models\Category;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarColorStockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->customer = User::factory()->create([
            'phone' => '081234567890',
        ]);

        $brand = Brand::factory()->create(['name' => 'Nissan']);
        $category = Category::factory()->create(['name' => 'SUV']);

        $this->car = Car::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'X-Trail 2.5',
            'year' => 2024,
            'price' => 400_000_000,
            'stock' => 3,
            'is_active' => true,
            'color_palette' => [
                ['name' => 'Putih Mutiara', 'hex' => '#F4F6F9', 'stock' => 2],
                ['name' => 'Hitam Metalik', 'hex' => '#1A1A1A', 'stock' => 1],
                ['name' => 'Merah Marun', 'hex' => '#991B1B', 'stock' => 0],
            ],
        ]);
    }

    public function test_detail_mobil_menampilkan_stok_warna_dan_indikator_habis(): void
    {
        $response = $this->get(route('cars.show', $this->car));

        $response->assertOk()
            ->assertSee('Putih Mutiara')
            ->assertSee('Hitam Metalik')
            ->assertSee('Merah Marun')
            ->assertSee('is-out-of-stock')
            ->assertSee('data-color-stock="2"', false)
            ->assertSee('data-color-stock="1"', false)
            ->assertSee('data-color-stock="0"', false);
    }

    public function test_pengajuan_pembelian_dengan_warna_tersedia_berhasil(): void
    {
        $response = $this->actingAs($this->customer)->post(route('purchase-requests.store', $this->car), [
            'payment_method' => PurchaseRequest::PAYMENT_CASH,
            'color' => 'Putih Mutiara',
            'phone' => '081234567890',
            'address' => 'Jl. Sudirman No. 123, Jakarta',
            'notes' => 'Tolong kirim siang hari',
        ]);

        $response->assertRedirect(route('account.purchase-requests.index'));

        $this->assertDatabaseHas('purchase_requests', [
            'user_id' => $this->customer->id,
            'car_id' => $this->car->id,
            'color' => 'Putih Mutiara',
            'status' => PurchaseRequest::STATUS_PENDING,
        ]);
    }

    public function test_pengajuan_pembelian_dengan_warna_habis_ditolak(): void
    {
        $response = $this->actingAs($this->customer)->post(route('purchase-requests.store', $this->car), [
            'payment_method' => PurchaseRequest::PAYMENT_CASH,
            'color' => 'Merah Marun',
            'phone' => '081234567890',
            'address' => 'Jl. Sudirman No. 123, Jakarta',
        ]);

        $response->assertSessionHasErrors('color');
        $this->assertDatabaseMissing('purchase_requests', [
            'user_id' => $this->customer->id,
            'color' => 'Merah Marun',
        ]);
    }

    public function test_persetujuan_pengajuan_mengurangi_stok_warna_spesifik_dan_total_stok(): void
    {
        $purchase = PurchaseRequest::factory()->create([
            'user_id' => $this->customer->id,
            'car_id' => $this->car->id,
            'color' => 'Putih Mutiara',
            'status' => PurchaseRequest::STATUS_PROCESSING,
        ]);

        $action = app(ChangePurchaseRequestStatus::class);
        $this->actingAs($this->admin);
        $action->handle($purchase, PurchaseRequest::STATUS_APPROVED);

        $this->car->refresh();
        $this->assertSame(2, $this->car->stock); // Total stock turun dari 3 ke 2
        $this->assertSame(1, $this->car->colorStock('Putih Mutiara')); // Putih Mutiara turun dari 2 ke 1
        $this->assertSame(1, $this->car->colorStock('Hitam Metalik')); // Hitam Metalik tetap 1
        $this->assertSame(0, $this->car->colorStock('Merah Marun')); // Merah Marun tetap 0

        // Saat dibatalkan, unit Putih Mutiara dikembalikan
        $action->handle($purchase, PurchaseRequest::STATUS_CANCELLED);
        $this->car->refresh();
        $this->assertSame(3, $this->car->stock);
        $this->assertSame(2, $this->car->colorStock('Putih Mutiara'));
    }

    public function test_admin_bisa_mengubah_stok_warna_di_panel_galeri(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.cars.images.colors.stock', $this->car), [
            'stocks' => [
                0 => 5, // Putih Mutiara: 5
                1 => 3, // Hitam Metalik: 3
                2 => 2, // Merah Marun: 2
            ],
        ]);

        $response->assertRedirect(route('admin.cars.images.index', $this->car))
            ->assertSessionHas('success');

        $this->car->refresh();
        $this->assertSame(10, $this->car->stock); // 5 + 3 + 2 = 10
        $this->assertSame(5, $this->car->colorStock('Putih Mutiara'));
        $this->assertSame(3, $this->car->colorStock('Hitam Metalik'));
        $this->assertSame(2, $this->car->colorStock('Merah Marun'));
    }
}

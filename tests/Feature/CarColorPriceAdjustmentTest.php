<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Car;
use App\Models\Category;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\CreditCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarColorPriceAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function createGtr(int $basePrice = 4_650_000_000): Car
    {
        $brand = Brand::factory()->create(['name' => 'Nissan']);
        $category = Category::factory()->create(['name' => 'Sport']);

        return Car::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'GT-R Premium Edition',
            'year' => 2024,
            'price' => $basePrice,
            'stock' => 5,
            'color_palette' => [
                ['name' => 'Putih Mutiara', 'hex' => '#F4F6F9', 'stock' => 2, 'extra_price' => 0],
                ['name' => 'Hijau Millennium Jade', 'hex' => '#7D8B78', 'stock' => 1, 'extra_price' => 35_000_000],
                ['name' => 'Biru Bayside Blue', 'hex' => '#145DA0', 'stock' => 1, 'extra_price' => 25_000_000],
                ['name' => 'Abu-abu Matte Special', 'hex' => '#7A7D7E', 'stock' => 1, 'extra_price' => 40_000_000],
            ],
            'is_active' => true,
        ]);
    }

    public function test_car_calculates_color_extra_price_and_final_price_for_color(): void
    {
        $car = $this->createGtr(4_650_000_000);

        $this->assertSame(0, $car->colorExtraPrice('Putih Mutiara'));
        $this->assertSame(35_000_000, $car->colorExtraPrice('Hijau Millennium Jade'));
        $this->assertSame(25_000_000, $car->colorExtraPrice('Biru Bayside Blue'));
        $this->assertSame(40_000_000, $car->colorExtraPrice('Abu-abu Matte Special'));
        $this->assertSame(0, $car->colorExtraPrice('Warna Tidak Dikenal'));

        $this->assertSame(4_650_000_000, $car->finalPriceForColor('Putih Mutiara'));
        $this->assertSame(4_685_000_000, $car->finalPriceForColor('Hijau Millennium Jade'));
        $this->assertSame(4_675_000_000, $car->finalPriceForColor('Biru Bayside Blue'));
        $this->assertSame(4_690_000_000, $car->finalPriceForColor('Abu-abu Matte Special'));
        $this->assertSame(4_650_000_000, $car->finalPriceForColor(null));
    }

    public function test_public_car_detail_page_renders_color_extra_prices_and_data_attributes(): void
    {
        $car = $this->createGtr(4_650_000_000);

        $response = $this->get(route('cars.show', $car));

        $response->assertOk();
        $response->assertSee('data-extra-price="35000000"', false);
        $response->assertSee('data-extra-price="25000000"', false);
        $response->assertSee('data-extra-price="40000000"', false);
        $response->assertSee('Hijau Millennium Jade');
        $response->assertSee('Rp 4.650.000.000');
    }

    public function test_purchase_request_create_page_displays_adjusted_price_and_options_with_extra_price(): void
    {
        $customer = User::factory()->create();
        $car = $this->createGtr(4_650_000_000);

        $response = $this->actingAs($customer)->get(route('purchase-requests.create', [
            'car' => $car,
            'warna' => 'Hijau Millennium Jade',
        ]));

        $response->assertOk();
        $response->assertSee('Rp 4.685.000.000');
        $response->assertDontSee('Cat Khusus');
        $response->assertSee('data-extra-price="35000000"', false);
    }

    public function test_primary_image_with_color_extra_price_loads_adjusted_price_on_detail_and_catalog(): void
    {
        $car = $this->createGtr(4_650_000_000);
        $car->images()->create([
            'path' => 'cars/gtr-green.webp',
            'thumb_path' => 'cars/thumbs/gtr-green.webp',
            'color_name' => 'Hijau Millennium Jade',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        // Halaman detail harus langsung memuat harga 4.685.000.000 tanpa badge "Cat Khusus"
        $detailResponse = $this->get(route('cars.show', $car));
        $detailResponse->assertOk();
        $detailResponse->assertSee('Rp 4.685.000.000');
        $detailResponse->assertDontSee('Cat Khusus');

        // Halaman katalog juga harus menampilkan harga 4.685.000.000 pada kartu mobil
        $catalogResponse = $this->get(route('cars.index'));
        $catalogResponse->assertOk();
        $catalogResponse->assertSee('Rp 4.685.000.000');
    }

    public function test_user_can_submit_cash_purchase_request_with_color_extra_price(): void
    {
        $customer = User::factory()->create();
        $car = $this->createGtr(4_650_000_000);

        $response = $this->actingAs($customer)->post(route('purchase-requests.store', $car), [
            'color' => 'Hijau Millennium Jade',
            'payment_method' => PurchaseRequest::PAYMENT_CASH,
            'phone' => '081234567890',
            'address' => 'Jalan Gatot Subroto No. 45 Jakarta Pusat',
            'notes' => 'Tolong disiapkan pengiriman unit.',
        ]);

        $response->assertRedirect(route('account.purchase-requests.index'));

        $this->assertDatabaseHas('purchase_requests', [
            'user_id' => $customer->id,
            'car_id' => $car->id,
            'color' => 'Hijau Millennium Jade',
            'car_price' => 4_685_000_000,
            'payment_method' => PurchaseRequest::PAYMENT_CASH,
        ]);
    }

    public function test_user_can_submit_credit_purchase_request_with_color_extra_price_and_valid_dp(): void
    {
        $customer = User::factory()->create();
        $car = $this->createGtr(4_650_000_000);

        $creditCalc = app(CreditCalculator::class);
        $adjustedPrice = 4_685_000_000;
        $minDp = $creditCalc->minDownPayment($adjustedPrice);

        $response = $this->actingAs($customer)->post(route('purchase-requests.store', $car), [
            'color' => 'Hijau Millennium Jade',
            'payment_method' => PurchaseRequest::PAYMENT_CREDIT,
            'down_payment' => $minDp,
            'tenor_months' => 12,
            'phone' => '081234567890',
            'address' => 'Jalan Gatot Subroto No. 45 Jakarta Pusat',
        ]);

        $response->assertRedirect(route('account.purchase-requests.index'));

        $this->assertDatabaseHas('purchase_requests', [
            'user_id' => $customer->id,
            'car_id' => $car->id,
            'color' => 'Hijau Millennium Jade',
            'car_price' => 4_685_000_000,
            'payment_method' => PurchaseRequest::PAYMENT_CREDIT,
            'down_payment' => $minDp,
            'tenor_months' => 12,
        ]);
    }

    public function test_credit_purchase_request_fails_if_dp_is_below_minimum_of_color_adjusted_price(): void
    {
        $customer = User::factory()->create();
        $car = $this->createGtr(4_650_000_000);

        // DP 20% dari harga dasar (4.650.000.000) adalah 930.000.000
        // Untuk Hijau Millennium Jade (4.685.000.000), DP minimal adalah 937.000.000
        $baseMinDp = 930_000_000;

        $response = $this->actingAs($customer)->from(route('purchase-requests.create', $car))->post(route('purchase-requests.store', $car), [
            'color' => 'Hijau Millennium Jade',
            'payment_method' => PurchaseRequest::PAYMENT_CREDIT,
            'down_payment' => $baseMinDp,
            'tenor_months' => 12,
            'phone' => '081234567890',
            'address' => 'Jalan Gatot Subroto No. 45 Jakarta Pusat',
        ]);

        $response->assertRedirect(route('purchase-requests.create', $car));
        $response->assertSessionHasErrors(['down_payment']);
    }

    public function test_admin_can_add_color_with_extra_price(): void
    {
        $admin = User::factory()->admin()->create();
        $car = $this->createGtr(4_650_000_000);

        // Uji dengan format string ribuan ber-titik "50.000.000"
        $response = $this->actingAs($admin)->post(route('admin.cars.images.colors.store', $car), [
            'name' => 'Midnight Purple Exclusive',
            'hex' => '#3B1E47',
            'stock' => 1,
            'extra_price' => '50.000.000',
        ]);

        $response->assertRedirect();
        $car->refresh();

        $this->assertSame(50_000_000, $car->colorExtraPrice('Midnight Purple Exclusive'));
        $this->assertSame(4_700_000_000, $car->finalPriceForColor('Midnight Purple Exclusive'));
    }

    public function test_admin_can_update_color_stocks_and_extra_prices(): void
    {
        $admin = User::factory()->admin()->create();
        $car = $this->createGtr(4_650_000_000);

        // Uji dengan format string ribuan ber-titik misal "45.000.000"
        $response = $this->actingAs($admin)->patch(route('admin.cars.images.colors.stock', $car), [
            'stocks' => [0 => 3, 1 => 2, 2 => 1, 3 => 1],
            'extra_prices' => [0 => 0, 1 => '45.000.000', 2 => '30.000.000', 3 => '55.000.000'],
        ]);

        $response->assertRedirect();
        $car->refresh();

        $this->assertSame(45_000_000, $car->colorExtraPrice('Hijau Millennium Jade'));
        $this->assertSame(30_000_000, $car->colorExtraPrice('Biru Bayside Blue'));
        $this->assertSame(55_000_000, $car->colorExtraPrice('Abu-abu Matte Special'));
        $this->assertSame(7, $car->stock);
    }

    public function test_admin_gallery_page_renders_thousands_formatted_extra_prices(): void
    {
        $admin = User::factory()->admin()->create();
        $car = $this->createGtr(4_650_000_000);

        $response = $this->actingAs($admin)->get(route('admin.cars.images.index', $car));

        $response->assertOk();
        $response->assertSee('data-number="thousands"', false);
        // Hijau Millennium Jade memiliki extra_price 35.000.000
        $response->assertSee('value="35.000.000"', false);
    }

    public function test_admin_can_reset_extra_prices_to_zero_by_clearing_input_or_sending_null(): void
    {
        $admin = User::factory()->admin()->create();
        $car = $this->createGtr(4_650_000_000);

        // Sebelum update: Hijau punya extra_price 35.000.000, Biru punya 25.000.000
        $this->assertSame(35_000_000, $car->colorExtraPrice('Hijau Millennium Jade'));
        $this->assertSame(25_000_000, $car->colorExtraPrice('Biru Bayside Blue'));

        // Kosongkan/hapus biaya cat (kirim null atau string kosong seperti perilaku form saat input dihapus)
        $response = $this->actingAs($admin)->patch(route('admin.cars.images.colors.stock', $car), [
            'stocks' => [0 => 2, 1 => 1, 2 => 1, 3 => 1],
            'extra_prices' => [0 => null, 1 => '', 2 => 0, 3 => 0],
        ]);

        $response->assertRedirect();
        $car->refresh();

        // Biaya cat harus benar-benar reset ke 0
        $this->assertSame(0, $car->colorExtraPrice('Hijau Millennium Jade'));
        $this->assertSame(0, $car->colorExtraPrice('Biru Bayside Blue'));
        $this->assertSame(0, $car->colorExtraPrice('Putih Mutiara'));
        $this->assertSame(0, $car->colorExtraPrice('Abu-abu Matte Special'));
    }

    public function test_credit_simulation_supports_color_variants_with_extra_prices(): void
    {
        $car = $this->createGtr(4_650_000_000);
        $car->images()->create([
            'path' => 'cars/gtr-jade.webp',
            'thumb_path' => 'cars/thumbs/gtr-jade.webp',
            'color_name' => 'Hijau Millennium Jade',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        // 1. Tanpa query warna: otomatis memilih foto utama (Hijau Millennium Jade, harga 4.685.000.000)
        $response1 = $this->get(route('credit.index', ['mobil' => $car->slug]));
        $response1->assertOk();
        $response1->assertSee('data-credit-color', false);
        $response1->assertSee('Hijau Millennium Jade · Rp 4.685.000.000');
        $response1->assertViewHas('price', 4_685_000_000);
        $response1->assertViewHas('selectedColor', 'Hijau Millennium Jade');

        // 2. Dengan query warna standar tanpa biaya tambahan (Putih Mutiara, harga dasar 4.650.000.000)
        $response2 = $this->get(route('credit.index', ['mobil' => $car->slug, 'warna' => 'Putih Mutiara']));
        $response2->assertOk();
        $response2->assertViewHas('price', 4_650_000_000);
        $response2->assertViewHas('selectedColor', 'Putih Mutiara');

        // 3. Dengan query warna edisi khusus (Abu-abu Matte Special, harga 4.690.000.000)
        $response3 = $this->get(route('credit.index', ['mobil' => $car->slug, 'warna' => 'Abu-abu Matte Special']));
        $response3->assertOk();
        $response3->assertViewHas('price', 4_690_000_000);
        $response3->assertViewHas('selectedColor', 'Abu-abu Matte Special');
    }
}

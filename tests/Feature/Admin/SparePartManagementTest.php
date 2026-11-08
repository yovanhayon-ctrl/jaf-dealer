<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SparePartManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $mechanic;

    private User $owner;

    private User $sales;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Admin Sparepart']);
        $this->mechanic = User::factory()->mechanic()->create(['name' => 'Mekanik Sparepart']);
        $this->owner = User::factory()->owner()->create(['name' => 'Owner']);
        $this->sales = User::factory()->sales()->create(['name' => 'Sales']);
        $this->customer = User::factory()->create();
    }

    public function test_admin_bisa_melihat_daftar_spare_part(): void
    {
        $part = SparePart::factory()->create([
            'part_number' => '15208-65F0A',
            'name' => 'Filter Oli Mesin Genuine',
            'price' => 85_000,
            'stock' => 20,
        ]);

        // Admin bisa akses
        $this->actingAs($this->admin)->get(route('admin.spare-parts.index'))
            ->assertOk()
            ->assertSee('Filter Oli Mesin Genuine')
            ->assertSee('15208-65F0A')
            ->assertSee('Rp 85.000')
            ->assertSee(route('admin.spare-parts.create'), false);

        // Role selain admin tidak punya akses di panel admin
        $this->actingAs($this->mechanic)->get(route('admin.spare-parts.index'))->assertForbidden();
        $this->actingAs($this->sales)->get(route('admin.spare-parts.index'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('admin.spare-parts.index'))->assertForbidden();
        $this->actingAs($this->customer)->get(route('admin.spare-parts.index'))->assertForbidden();

        // Guest diarahkan ke login
        auth()->logout();
        $this->get(route('admin.spare-parts.index'))->assertRedirect(route('login'));
    }

    public function test_admin_bisa_menambah_spare_part_baru(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('kampas-rem.jpg', 600, 600);

        $response = $this->actingAs($this->admin)->post(route('admin.spare-parts.store'), [
            'part_number' => 'd1060-1ka0a',
            'name' => 'Kampas Rem Depan Livina',
            'category' => 'pengereman',
            'compatibility' => 'Nissan Grand Livina 1.5 & 1.8',
            'price' => '450.000',
            'stock' => '15',
            'min_stock' => '5',
            'unit' => 'set',
            'description' => 'Original Nissan Genuine Parts Brake Pad.',
            'image' => $image,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.spare-parts.index'))
            ->assertSessionHas('success', 'Spare part "Kampas Rem Depan Livina" berhasil ditambahkan.');

        $part = SparePart::where('part_number', 'D1060-1KA0A')->first();
        $this->assertNotNull($part);
        $this->assertSame('Kampas Rem Depan Livina', $part->name);
        $this->assertSame('kampas-rem-depan-livina', $part->slug);
        $this->assertSame(450_000, $part->price);
        $this->assertSame(15, $part->stock);
        $this->assertSame('pengereman', $part->category);
        $this->assertNotNull($part->image);
        Storage::disk('public')->assertExists($part->image);

        // Tercatat di activity log
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'spare_part.created',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_validasi_tambah_spare_part(): void
    {
        SparePart::factory()->create(['part_number' => 'SKU-001']);

        $response = $this->actingAs($this->admin)->post(route('admin.spare-parts.store'), [
            'part_number' => 'SKU-001', // duplikat
            'name' => '',               // kosong
            'category' => 'kategori_palsu',
            'price' => 'bukan-angka',
            'stock' => '-5',
            'unit' => 'unit_palsu',
        ]);

        $response->assertSessionHasErrors(['part_number', 'name', 'category', 'price', 'stock', 'unit']);
    }

    public function test_admin_bisa_mengubah_spare_part(): void
    {
        $part = SparePart::factory()->create([
            'part_number' => 'SKU-ORIGINAL',
            'name' => 'Busi Iridium',
            'price' => 120_000,
            'stock' => 10,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.spare-parts.update', $part), [
            'part_number' => 'SKU-ORIGINAL',
            'name' => 'Busi Iridium Laser',
            'category' => 'kelistrikan',
            'price' => '135.000',
            'stock' => '25',
            'min_stock' => '8',
            'unit' => 'pcs',
            'compatibility' => 'Nissan March, Livina',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.spare-parts.index'))
            ->assertSessionHas('success', 'Spare part "Busi Iridium Laser" berhasil diperbarui.');

        $part->refresh();
        $this->assertSame('Busi Iridium Laser', $part->name);
        $this->assertSame(135_000, $part->price);
        $this->assertSame(25, $part->stock);
        $this->assertSame(8, $part->min_stock);

        // Tercatat di activity log
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'spare_part.updated',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_bisa_toggle_status_aktif_dan_menghapus_spare_part(): void
    {
        $part = SparePart::factory()->create([
            'name' => 'Filter Udara',
            'is_active' => true,
        ]);

        // Toggle nonaktif
        $this->actingAs($this->admin)->patch(route('admin.spare-parts.toggle-active', $part))
            ->assertRedirect();
        $this->assertFalse($part->fresh()->is_active);

        // Toggle aktif kembali
        $this->actingAs($this->admin)->patch(route('admin.spare-parts.toggle-active', $part))
            ->assertRedirect();
        $this->assertTrue($part->fresh()->is_active);

        // Hapus
        $this->actingAs($this->admin)->delete(route('admin.spare-parts.destroy', $part))
            ->assertRedirect(route('admin.spare-parts.index'))
            ->assertSessionHas('success', 'Spare part "Filter Udara" berhasil dihapus.');

        $this->assertModelMissing($part);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'spare_part.deleted',
            'category' => ActivityLog::CATEGORY_DELETE,
        ]);
    }

    public function test_filter_dan_pencarian_spare_part(): void
    {
        SparePart::factory()->create([
            'part_number' => 'FILTER-01',
            'name' => 'Filter Bensin',
            'category' => 'mesin',
            'stock' => 10,
            'min_stock' => 5,
        ]);

        SparePart::factory()->create([
            'part_number' => 'OLI-5W30',
            'name' => 'Oli Mesin 5W-30',
            'category' => 'oli',
            'stock' => 2,
            'min_stock' => 5, // menipis
        ]);

        SparePart::factory()->create([
            'part_number' => 'BUSI-HABIS',
            'name' => 'Busi Nickel',
            'category' => 'kelistrikan',
            'stock' => 0,     // habis
        ]);

        // Cari kata kunci
        $this->actingAs($this->admin)->get(route('admin.spare-parts.index', ['q' => 'Bensin']))
            ->assertOk()
            ->assertSee('Filter Bensin')
            ->assertDontSee('Oli Mesin 5W-30');

        // Filter kategori
        $this->actingAs($this->admin)->get(route('admin.spare-parts.index', ['kategori' => 'oli']))
            ->assertOk()
            ->assertSee('Oli Mesin 5W-30')
            ->assertDontSee('Filter Bensin');

        // Filter stok menipis
        $this->actingAs($this->admin)->get(route('admin.spare-parts.index', ['stok' => 'menipis']))
            ->assertOk()
            ->assertSee('Oli Mesin 5W-30')
            ->assertDontSee('Filter Bensin')
            ->assertDontSee('Busi Nickel');

        // Filter stok habis
        $this->actingAs($this->admin)->get(route('admin.spare-parts.index', ['stok' => 'habis']))
            ->assertOk()
            ->assertSee('Busi Nickel')
            ->assertDontSee('Filter Bensin');
    }

    public function test_selain_admin_tidak_bisa_mengelola_spare_part(): void
    {
        $part = SparePart::factory()->create();

        foreach ([$this->mechanic, $this->sales, $this->owner, $this->customer] as $user) {
            $this->actingAs($user)->get(route('admin.spare-parts.create'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.spare-parts.store'), [])->assertForbidden();
            $this->actingAs($user)->get(route('admin.spare-parts.edit', $part))->assertForbidden();
            $this->actingAs($user)->put(route('admin.spare-parts.update', $part), [])->assertForbidden();
            $this->actingAs($user)->patch(route('admin.spare-parts.toggle-active', $part))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.spare-parts.destroy', $part))->assertForbidden();
        }
    }
}

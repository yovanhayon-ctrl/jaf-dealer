<?php

namespace Database\Seeders;

use App\Models\SparePart;
use Illuminate\Database\Seeder;

class SparePartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parts = [
            [
                'part_number' => '15208-65F0A',
                'name' => 'Filter Oli Mesin Genuine',
                'category' => 'mesin',
                'compatibility' => 'Nissan Grand Livina, March, Juke, Serena, X-Trail',
                'price' => 85_000,
                'stock' => 35,
                'min_stock' => 10,
                'unit' => 'pcs',
                'description' => 'Filter oli original standar pabrikan untuk menyaring kotoran dan menjaga kebersihan oli mesin secara optimal.',
                'is_active' => true,
            ],
            [
                'part_number' => 'D1060-1KA0A',
                'name' => 'Kampas Rem Depan (Brake Pad)',
                'category' => 'pengereman',
                'compatibility' => 'Nissan Grand Livina 1.5/1.8, Livina L11, Evalia',
                'price' => 450_000,
                'stock' => 18,
                'min_stock' => 5,
                'unit' => 'set',
                'description' => 'Kampas rem depan orisinal tanpa asbes dengan daya pengereman tinggi dan minim debu residu.',
                'is_active' => true,
            ],
            [
                'part_number' => 'D4060-1KA0A',
                'name' => 'Kampas Rem Belakang (Brake Shoe)',
                'category' => 'pengereman',
                'compatibility' => 'Nissan Grand Livina, Evalia, March',
                'price' => 380_000,
                'stock' => 12,
                'min_stock' => 5,
                'unit' => 'set',
                'description' => 'Kampas rem tromol belakang berkualitas tinggi menjamin keamanan berkendara.',
                'is_active' => true,
            ],
            [
                'part_number' => 'PLZKAR6A-11',
                'name' => 'Busi Laser Iridium NGK',
                'category' => 'kelistrikan',
                'compatibility' => 'Nissan Grand Livina HR15DE, Juke, March 1.5',
                'price' => 135_000,
                'stock' => 40,
                'min_stock' => 12,
                'unit' => 'pcs',
                'description' => 'Busi elektroda iridium tipis memberikan pengapian sempurna, hemat bahan bakar, dan tahan hingga 100.000 km.',
                'is_active' => true,
            ],
            [
                'part_number' => '16546-ED000',
                'name' => 'Filter Udara Mesin (Air Filter)',
                'category' => 'mesin',
                'compatibility' => 'Nissan Grand Livina, Evalia, Latio',
                'price' => 145_000,
                'stock' => 20,
                'min_stock' => 6,
                'unit' => 'pcs',
                'description' => 'Elemen penyaring udara presisi tinggi menjaga pasokan udara bersih ke ruang bakar.',
                'is_active' => true,
            ],
            [
                'part_number' => '27891-ED50A',
                'name' => 'Filter AC Kabin Anti Bakteri',
                'category' => 'aksesoris',
                'compatibility' => 'Nissan Grand Livina, Juke, Serena C26',
                'price' => 110_000,
                'stock' => 15,
                'min_stock' => 5,
                'unit' => 'pcs',
                'description' => 'Filter AC karbon aktif menyaring debu halus, serbuk, dan bau tidak sedap di dalam kabin mobil.',
                'is_active' => true,
            ],
            [
                'part_number' => 'KLAM2-00204',
                'name' => 'Oli Mesin 0W-20 Fully Synthetic 4L',
                'category' => 'oli',
                'compatibility' => 'Universal (Mesin Bensin Modern, Eco Car, Hybrid)',
                'price' => 480_000,
                'stock' => 25,
                'min_stock' => 8,
                'unit' => 'botol',
                'description' => 'Pelumas mesin sintetis penuh viskositas rendah untuk perlindungan keausan ekstrem dan efisiensi bahan bakar.',
                'is_active' => true,
            ],
            [
                'part_number' => 'KLE53-00004',
                'name' => 'Oli Transmisi CVT Fluid NS-3 4L',
                'category' => 'oli',
                'compatibility' => 'Nissan X-Trail T32, Serena C26/C27, Teana, New Livina',
                'price' => 560_000,
                'stock' => 10,
                'min_stock' => 4,
                'unit' => 'kaleng',
                'description' => 'Formula fluida transmisi otomatis CVT khusus Nissan generasi terbaru.',
                'is_active' => true,
            ],
            [
                'part_number' => 'MF-46B24L',
                'name' => 'Aki Kering Maintenance Free 45 Ah',
                'category' => 'kelistrikan',
                'compatibility' => 'Toyota Avanza, Rush, Daihatsu Xenia, Terios, Suzuki Ertiga',
                'price' => 890_000,
                'stock' => 8,
                'min_stock' => 3,
                'unit' => 'pcs',
                'description' => 'Aki siap pakai bebas perawatan dengan arus start dingin tinggi dan daya tahan lama.',
                'is_active' => true,
            ],
            [
                'part_number' => 'WPR-2414-SET',
                'name' => 'Wiper Blade Hybrid Set (24 & 14 Inci)',
                'category' => 'aksesoris',
                'compatibility' => 'Nissan Grand Livina, Toyota Avanza, Honda Jazz',
                'price' => 175_000,
                'stock' => 14,
                'min_stock' => 5,
                'unit' => 'set',
                'description' => 'Bilah penyeka kaca berteknologi hibrida dengan lapisan silikon teflon untuk sapuan hening dan bersih.',
                'is_active' => true,
            ],
            [
                'part_number' => '54302-1KA0A',
                'name' => 'Shockbreaker Depan Gas',
                'category' => 'suspensi',
                'compatibility' => 'Nissan Grand Livina L10 & L11',
                'price' => 850_000,
                'stock' => 6,
                'min_stock' => 2,
                'unit' => 'pcs',
                'description' => 'Peredam kejut tipe gas orisinal meredam guncangan jalan dan menjaga stabilitas manuver kendaraan.',
                'is_active' => true,
            ],
            [
                'part_number' => 'DOT4-BRAKE-1L',
                'name' => 'Minyak Rem DOT 4 (1 Liter)',
                'category' => 'oli',
                'compatibility' => 'Universal (Semua Kendaraan Sistem Rem Hidrolik)',
                'price' => 95_000,
                'stock' => 22,
                'min_stock' => 6,
                'unit' => 'botol',
                'description' => 'Cairan rem titik didih tinggi mencegah vapor lock saat pengereman berat terus menerus.',
                'is_active' => true,
            ],
            [
                'part_number' => '7PK1140-BELT',
                'name' => 'Drive Belt / Fan Belt 7PK1140',
                'category' => 'mesin',
                'compatibility' => 'Nissan Grand Livina 1.5, Evalia',
                'price' => 165_000,
                'stock' => 3, // menipis
                'min_stock' => 5,
                'unit' => 'pcs',
                'description' => 'Tali kipas alternator & kompresor AC dengan serat EPDM tahan panas dan anti retak.',
                'is_active' => true,
            ],
            [
                'part_number' => 'COOL-PREMIX-4L',
                'name' => 'Long Life Radiator Coolant 4L',
                'category' => 'oli',
                'compatibility' => 'Universal (Sistem Pendingin Mesin Mobil)',
                'price' => 125_000,
                'stock' => 0, // habis untuk menguji filter stok habis
                'min_stock' => 5,
                'unit' => 'botol',
                'description' => 'Cairan pendingin radiator siap pakai anti karat dan anti buih dengan perlindungan maksimal.',
                'is_active' => true,
            ],
        ];

        foreach ($parts as $partData) {
            SparePart::firstOrCreate(
                ['part_number' => $partData['part_number']],
                [
                    ...$partData,
                    'slug' => SparePart::uniqueSlug($partData['name']),
                ]
            );
        }
    }
}

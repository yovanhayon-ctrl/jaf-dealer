# Panduan Pengujian Manual (Black-Box Testing) JAF Dealer

Panduan ini disusun untuk memandu pengujian fungsional secara manual (*Black-Box Testing*) pada seluruh fitur aplikasi **JAF Dealer** melalui web browser di lingkungan lokal (`http://127.0.0.1:8000` atau `http://localhost:8000`).

---

## Daftar Akun Uji Coba (Kredensial Login)

Semua akun menggunakan kata sandi (*password*) yang sama untuk kemudahan pengujian: **`password`**

| Aktor / Role | Nama Akun | Alamat Email | Kata Sandi | Deskripsi Hak Akses |
| :--- | :--- | :--- | :---: | :--- |
| **Owner** | Pemilik Dealer | `cruzgzz244@gmail.com` | `password` | Pengawas bisnis: Dashboard eksekutif, laporan keuangan/omset, ekspor Excel, audit log aktivitas. |
| **Admin** | Administrator | `admin@gmail.com` | `password` | Operasional penuh: Kelola master mobil, galeri warna, setujui pembelian, verifikasi transfer, cetak SPK, kelola staf. |
| **Customer Service** | Petugas CS | `costumerservice@gmail.com` | `password` | Penerimaan: Konfirmasi jadwal test drive (tugaskan sales), konfirmasi booking servis (tugaskan mekanik). |
| **Sales 1** | Sales Eksekutif | `sales@gmail.com` | `password` | Penjualan: Menangani test drive pelanggan, menindaklanjuti pengajuan pembelian. |
| **Sales 2** | Sales Pendamping | `sales2@gmail.com` | `password` | Akun pembanding untuk menguji leaderboard "Sales Terbaik". |
| **Mekanik 1** | Teknisi Utama | `mekanik1@gmail.com` | `password` | Bengkel: Melihat pekerjaan servis yang ditugaskan, memantau rating & ulasan pribadi. |
| **Mekanik 2** | Teknisi Pendamping | `mekanik2@gmail.com` | `password` | Akun pembanding untuk menguji ketersediaan mekanik & bentrok jadwal. |
| **Customer Utama** | Yoseph Kewena Hayon | `yovanhayon@gmail.com` | `password` | Pelanggan: Ajukan pembelian, booking test drive, booking servis, favorit, testimoni, rating. |

---

## Bagian 1: Halaman Publik (Tanpa Login / Guest)

Pada bagian ini, pastikan browser dalam kondisi **Logout** atau gunakan jendela *Incognito / Private Window*.

### 1.1 Beranda (`/`)
- **URL:** `http://127.0.0.1:8000/`
- **Langkah Pengujian:**
  1. Buka halaman Beranda.
  2. Klik tombol **Toggle Dark/Light Mode** di pojok kanan atas (navbar).
  3. Gulir ke bawah dan periksa bagian:
     - Hero Section & Tombol Aksi Cepat (*Cari Mobil*, *Simulasi Kredit*, *Booking Servis*).
     - Daftar **Mobil Unggulan** (menampilkan lencana kondisi Baru/Bekas dan harga resmi).
     - Banner **Promo Aktif** yang sedang berlaku.
     - Bagian **Ulasan Pelanggan (Testimoni)** yang telah disetujui.
     - Footer informasi alamat bengkel, jam buka, dan tautan WhatsApp.
- **Hasil yang Diharapkan (Expected Result):**
  - Tema beralih seketika antara gelap dan terang tanpa kedipan (*flicker*) dan preferensi tersimpan di browser.
  - Seluruh kartu mobil menampilkan foto, harga berformat Rupiah, dan tautan yang berfungsi ke halaman detail.

---

### 1.2 Katalog Mobil & Bandingkan Mobil (`/mobil` & `/bandingkan`)
- **URL Katalog:** `http://127.0.0.1:8000/mobil`
- **Langkah Pengujian Filter & Pencarian:**
  1. Di bilah filter kiri, pilih Kategori: **SUV**, lalu klik *Terapkan Filter*.
  2. Ubah kondisi mobil menjadi **Bekas** (Heritage).
  3. Masukkan kata kunci pada kotak pencarian: `GT-R` lalu tekan Enter.
  4. Ubah urutan (*Sorting*) menjadi **Harga Termurah** lalu **Tahun Terbaru**.
- **Hasil yang Diharapkan (Katalog):**
  - Daftar mobil menyaring unit secara akurat sesuai filter yang dipilih.
  - Jumlah total unit yang ditemukan sesuai dengan hasil filter.
  - Klik *Reset Filter* mengembalikan daftar ke seluruh 14 mobil Nissan.

- **URL Bandingkan Mobil:** `http://127.0.0.1:8000/bandingkan`
- **Langkah Pengujian Bandingkan Unit:**
  1. Pada kartu mobil di katalog, klik tombol **Bandingkan** (ikon timbangan) pada mobil ke-1 (*Nissan Kicks e-Power VL*).
  2. Tambahkan mobil ke-2 (*Nissan Serena e-Power*), ke-3 (*Nissan GT-R*), dan ke-4 (*Nissan Leaf*).
  3. Buka halaman `/bandingkan`.
  4. **Tes Negatif (Batas Maksimal 4 Unit):** Kembali ke katalog dan coba tambahkan mobil ke-5.
  5. Di halaman bandingkan, klik tombol **Hapus** pada salah satu mobil.
  6. Klik tombol **Kosongkan Perbandingan**.
- **Hasil yang Diharapkan (Bandingkan):**
  - Halaman perbandingan menampilkan kolom komparasi spesifikasi (mesin, transmisi, dimensi, bahan bakar, harga) secara sejajar.
  - Sistem menolak penambahan mobil ke-5 dengan pesan peringatan: *"Maksimal 4 mobil yang dapat dibandingkan secara bersamaan"*.
  - Menghapus unit memperbarui tabel secara instan, dan tombol kosongkan mengembalikan halaman ke kondisi kosong.

---

### 1.3 Detail Mobil & Mekanisme Pilihan Warna / Swatches (`/mobil/{slug}`)
- **URL Uji Coba 1 (Mobil Standar):** `http://127.0.0.1:8000/mobil/nissan-kicks-e-power-vl-2025`
- **URL Uji Coba 2 (Mobil Warna Premium):** `http://127.0.0.1:8000/mobil/nissan-gt-r-premium-edition-2024`
- **URL Uji Coba 3 (Mobil Heritage R34):** `http://127.0.0.1:8000/mobil/nissan-skyline-gt-r-v-spec-ii-r34-2000`

- **Langkah Pengujian Mekanisme Warna (Color Swatches):**
  1. Buka halaman mobil **Nissan GT-R Premium Edition 2024**.
  2. Perhatikan foto utama awal, label nama warna awal (*Putih Mutiara*), dan harga dasar: **Rp 4.650.000.000**.
  3. Klik lingkaran warna ke-1 (*Biru Metalik - Bayside Blue*):
     - Amati perubahan foto galeri utama.
     - Amati badge stok dan teks nama warna.
  4. **Uji Warna Premium dengan Biaya Tambahan:** Klik lingkaran warna ke-5 (**Ungu Mutiara - Midnight Purple**):
     - Amati foto utama berganti ke varian Midnight Purple.
     - Amati harga mobil utama di bagian atas: otomatis naik menjadi **Rp 4.685.000.000** (+Rp 35.000.000).
     - Gulir ke tabel **Ringkasan Cicilan** di kolom kanan bawah: perhatikan bahwa nominal DP minimal dan angsuran per bulan (12 s.d 60 bulan) **otomatis dihitung ulang secara real-time**.
     - Amati kolom *Warna* pada tabel spesifikasi di bawah: teks berubah menjadi *Ungu Mutiara (Midnight Purple)*.
  5. Cek tombol utama **Ajukan Pembelian**:
     - Arahkan kursor / periksa tautan tombol: tautan otomatis memuat parameter query `?warna=Ungu+Mutiara+(Midnight+Purple)`.
- **Hasil yang Diharapkan:**
  - Galeri berganti foto dengan transisi halus tanpa gambar blank atau error console browser.
  - Harga warna premium dan tabel cicilan kalkulasi ulang sinkron 100% tanpa perlu me-refresh halaman.

---

### 1.4 Halaman Informasi Pendukung Publik
- **Simulasi Kredit (`/simulasi-kredit`):**
  - Geser slider atau ketik harga mobil, masukkan nominal DP, pilih tenor 12–60 bulan.
  - **Tes Negatif:** Masukkan DP di bawah 20% dari harga mobil -> sistem menampilkan pesan batas minimal DP 20%.
- **Katalog Layanan Servis (`/servis`):** Menampilkan daftar 6 paket servis berkala, estimasi durasi waktu, dan rincian pekerjaan.
- **Katalog Suku Cadang (`/spare-part`):** Menampilkan 14 suku cadang resmi, nomor part (SKU), status ketersediaan stok, dan filter kategori.
- **Tentang Kami (`/tentang-kami`) & Kontak (`/kontak`):** Memuat profil dealer JAF, peta lokasi, jam operasional, dan form kontak.

---

## Bagian 2: Role Customer (`yovanhayon@gmail.com`)

Masuk (*Login*) menggunakan akun customer:
- **Email:** `yovanhayon@gmail.com`
- **Password:** `password`

### 2.1 Manajemen Akun & Favorit
- **URL:** `http://127.0.0.1:8000/akun/profil`
- **Langkah Pengujian:**
  1. Login ke sistem. Menu navbar menampilkan nama customer dan menu dropdown akun.
  2. Buka menu **Profil Saya** (`/akun/profil`):
     - Ubah nomor telepon dan alamat domisili, lalu klik *Simpan Profil*.
  3. Buka halaman katalog `/mobil`, klik tombol **Favorit** (ikon hati) pada salah satu mobil.
  4. Buka halaman **Mobil Favorit Saya** (`/akun/favorit`).
- **Hasil yang Diharapkan:**
  - Notifikasi sukses muncul dan data profil tersimpan ke database.
  - Mobil yang disukai muncul di daftar favorit dan ikon hati berubah warna menjadi merah.

---

### 2.2 Booking Test Drive
- **URL:** `http://127.0.0.1:8000/test-drive`
- **Langkah Pengujian:**
  1. Buka formulir pendaftaran Test Drive.
  2. Pilih Mobil: *Nissan Serena e-Power Highway Star 2025*.
  3. Pilih Tanggal: Besok (H+1).
  4. Pilih Jam Kedatangan: *10.00 WIB*.
  5. **Tes Negatif (Wajib Centang SIM A):**
     - Jangan centang kotak persetujuan kepemilikan SIM A.
     - Klik tombol *Kirim Pendaftaran Test Drive*.
     - **Hasil yang Diharapkan:** Formulir ditolak dengan pesan error validasi: *"Anda wajib menyatakan memiliki SIM A aktif untuk melakukan test drive"*.
  6. **Tes Positif (Berhasil):**
     - Centang kotak konfirmasi SIM A, isi catatan tambahan (opsional), lalu klik kirim.
     - **Hasil yang Diharapkan:** Halaman dialihkan ke `/akun/test-drive` dengan status **Menunggu Konfirmasi** berwarna kuning.
  7. **Tes Negatif (Anti-Bentrok Jadwal):**
     - Coba ajukan kembali test drive untuk unit mobil yang sama pada tanggal dan jam yang persis sama.
     - **Hasil yang Diharapkan:** Sistem menolak pengajuan dengan pesan bahwa slot jadwal unit tersebut sudah terisi.

---

### 2.3 Pengajuan Pembelian Mobil (Cash & Kredit)
- **URL:** `http://127.0.0.1:8000/mobil/nissan-kicks-e-power-vl-2025`

#### Skenario A: Pembelian Metode Cash
1. Di halaman detail mobil *Kicks e-Power*, pilih warna: **Oranye Metalik Atap Hitam (Monarch Orange)**.
2. Klik tombol **Ajukan Pembelian**.
3. Di halaman `/mobil/{slug}/ajukan`:
   - Periksa dropdown *Pilihan Warna Mobil*: warna *Oranye Metalik Atap Hitam* harus **otomatis terpilih**.
   - Pilih metode pembayaran: **Cash / Tunai**.
   - Bagian input DP dan tenor cicilan kredit otomatis disembunyikan.
   - Isi nomor telepon dan alamat pengiriman lengkap.
   - Klik tombol **Kirim Pengajuan Pembelian**.
4. **Hasil yang Diharapkan:**
   - Pengajuan tersimpan di database dengan status **Menunggu Persetujuan**.
   - Muncul di halaman riwayat `/akun/pengajuan`.

#### Skenario B: Pembatalan Pengajuan oleh Pelanggan
1. Pada pengajuan yang baru saja dibuat di `/akun/pengajuan`, klik tombol **Batalkan Pengajuan**.
2. Masukkan alasan pembatalan pada modal konfirmasi, lalu klik *Ya, Batalkan*.
3. **Hasil yang Diharapkan:**
   - Status pengajuan seketika berubah menjadi **Dibatalkan Pelanggan** (badge abu-abu).
   - Tombol pembatalan hilang dan riwayat pembatalan tercatat.

#### Skenario C: Pembelian Metode Kredit (Untuk Diuji Bersama Admin)
1. Ajukan kembali pembelian mobil *Nissan Kicks e-Power VL 2025*.
2. Pilih metode: **Kredit**.
3. Masukkan uang muka (DP) minimal 20% dan pilih tenor **36 bulan**.
4. Klik *Kirim Pengajuan Pembelian* (Biarkan pengajuan ini berstatus *Menunggu Persetujuan* untuk diproses oleh Admin nanti).

---

### 2.4 Booking Servis Kendaraan (JAF Service)
- **URL:** `http://127.0.0.1:8000/servis/booking`
- **Langkah Pengujian:**
  1. Buka halaman formulir booking servis.
  2. **Tes Input Format Plat Nomor Otomatis Standar Indonesia:**
     - Ketik pada kotak Nomor Polisi: `b1234abc` (ketik huruf kecil tanpa spasi).
     - **Hasil yang Diharapkan:** Input JavaScript otomatis memformat teks menjadi huruf kapital dan memberi spasi 3 segmen yang rapi: **`B 1234 ABC`**.
  3. **Tes Booking Tanggal Hari Ini (Same-day Service):**
     - Pilih tanggal servis: **Hari Ini** (tanggal sekarang).
     - Periksa dropdown jam kedatangan: jam-jam operasional yang sudah terlewat atau memiliki jeda waktu kedatangan kurang dari 1 jam otomatis disembunyikan.
  4. Pilih Paket Layanan: **Servis Berkala**.
  5. Masukkan KM Odometer: `25000`.
  6. Pilih Preferensi Mekanik: Pilih **Mekanik 1** (atau biarkan *Bebas*).
  7. Masukkan keluhan: *"Ganti oli mesin dan cek sistem rem bunyi decit"*.
  8. Klik tombol **Kirim Booking Servis**.
- **Hasil yang Diharapkan:**
  - Formulir berhasil di-submit dan dialihkan ke `/akun/servis` dengan status **Menunggu Konfirmasi**.

---

### 2.5 Unggah Bukti Pembayaran, Testimoni Mobil, & Rating Mekanik
*(Langkah ini dijalankan setelah pengajuan disetujui Admin dan servis diselesaikan oleh Mekanik pada Bagian 5 & 6)*:
- **Unggah Bukti Transfer (`/akun/pengajuan`):** Saat status pengajuan diubah Admin menjadi *Disetujui*, klik tombol *Unggah Bukti Pembayaran*, pilih file foto struk/transfer bank (`.jpg`/`.png`), lalu submit.
- **Beri Testimoni Pembelian:** Setelah status pengajuan selesai/lunas, klik tombol *Beri Ulasan* -> pilih bintang 5 dan tulis testimoni puas.
- **Beri Rating Mekanik:** Pada halaman riwayat servis `/akun/servis`, setelah status menjadi *Selesai*, klik tombol *Beri Ulasan Mekanik* -> pilih bintang 5 dan isi catatan kepuasan terhadap teknisi.

---

## Bagian 3: Role Customer Service (`costumerservice@gmail.com`)

Keluar dari akun customer, lalu login sebagai Customer Service:
- **Email:** `costumerservice@gmail.com`
- **Password:** `password`

### 3.1 Konfirmasi Test Drive & Penugasan Sales
- **URL:** `http://127.0.0.1:8000/admin/test-drive`
- **Langkah Pengujian:**
  1. Periksa ikon lonceng **Notifikasi** di navbar admin: notifikasi test drive baru dari Yoseph muncul.
  2. Buka menu **Test Drive** (`/admin/test-drive`).
  3. Klik tombol *Detail* pada pendaftaran test drive berstatus *Menunggu Konfirmasi*.
  4. Pada kartu *Penugasan Sales*, buka dropdown sales: pilih **Sales** (`sales@gmail.com`).
  5. Klik tombol **Tugaskan Sales**.
  6. Pada kartu status, ubah status dari *Menunggu Konfirmasi* menjadi **Dikonfirmasi**, lalu klik *Perbarui Status*.
- **Hasil yang Diharapkan:**
  - Status test drive berubah menjadi **Dikonfirmasi**.
  - Akun Sales yang ditugaskan tercatat secara resmi pada data test drive.

---

### 3.2 Konfirmasi Booking Servis & Penugasan Mekanik
- **URL:** `http://127.0.0.1:8000/admin/servis`
- **Langkah Pengujian:**
  1. Buka menu **Booking Servis** (`/admin/servis`).
  2. Buka detail servis kendaraan plat nomor `B 1234 ABC` yang diajukan oleh customer.
  3. Gulir ke bagian kartu **Mekanik & Pekerjaan**:
     - Periksa label dropdown pilihan mekanik:
       * Mekanik yang sedang tidak ada servis: tampil label `[Nama Mekanik] (Tersedia)`.
     - Pilih **Mekanik 1**, lalu klik tombol **Tugaskan**.
  4. Ubah status booking dari *Menunggu Konfirmasi* menjadi **Dikonfirmasi**.
- **Hasil yang Diharapkan:**
  - Mekanik 1 resmi ditugaskan ke booking servis tersebut.
  - Daftar butir checklist pekerjaan standar otomatis disalin dari master paket layanan ke booking tersebut.

---

### 3.3 Uji Batasan Hak Akses (Role Restriction Test)
- **Langkah Pengujian:**
  1. Buka menu Pengajuan Pembelian (`/admin/pengajuan/{id}`).
     - **Hasil yang Diharapkan:** Tombol "Setujui Pengajuan" **tidak tersedia** bagi Customer Service (hanya Admin/Super Admin yang berwenang).
  2. Ketik langsung URL Laporan Keuangan di address bar browser: `http://127.0.0.1:8000/admin/laporan`
     - **Hasil yang Diharapkan:** Sistem menolak akses dengan halaman error **`403 Forbidden / Akses Ditolak`**.
  3. Ketik langsung URL Manajemen Staf: `http://127.0.0.1:8000/admin/staf`
     - **Hasil yang Diharapkan:** Sistem menolak akses dengan halaman error **`403 Forbidden`**.

---

## Bagian 4: Role Sales (`sales@gmail.com`)

Keluar dari Customer Service, lalu login sebagai Sales:
- **Email:** `sales@gmail.com`
- **Password:** `password`

### 4.1 Penyelesaian Test Drive Pelanggan
- **URL:** `http://127.0.0.1:8000/admin/test-drive`
- **Langkah Pengujian:**
  1. Masuk ke menu **Test Drive**.
  2. Buka data test drive yang telah ditugaskan ke Anda oleh Customer Service sebelumnya.
  3. Pada form pembaruan status: ubah status dari *Dikonfirmasi* menjadi **Selesai**.
  4. Masukkan catatan follow-up hasil test drive: *"Customer puas dengan akselerasi Serena e-Power, berminat ambil paket kredit"*.
  5. Klik tombol *Perbarui Status*.
- **Hasil yang Diharapkan:**
  - Status berubah menjadi **Selesai** (badge hijau).
  - Metrik test drive selesai pada leaderboard performa sales otomatis bertambah 1.

---

### 4.2 Pantau Pengajuan Pembelian & Batasan Hak Akses
- **URL:** `http://127.0.0.1:8000/admin/pengajuan`
- **Langkah Pengujian:**
  1. Masuk ke menu **Pengajuan Mobil**.
  2. Buka pengajuan pembelian atas nama Yoseph Kewena Hayon.
  3. Tugaskan diri Anda sebagai Sales penanggung jawab pada form *Sales Penanggung Jawab*.
  4. **Uji Keamanan Akses:**
     - Ketik URL `http://127.0.0.1:8000/admin/laporan` -> **`403 Forbidden`**.
     - Ketik URL `http://127.0.0.1:8000/admin/staf` -> **`403 Forbidden`**.
     - Buka menu Mobil (`/admin/mobil`) -> Sales hanya dapat melihat katalog, tombol hapus/tambah mobil dinonaktifkan.

---

## Bagian 5: Role Mekanik (`mekanik1@gmail.com`)

Keluar dari Sales, lalu login sebagai Mekanik:
- **Email:** `mekanik1@gmail.com`
- **Password:** `password`

### 5.1 Panel Pekerjaan Servis Mekanik
- **URL:** `http://127.0.0.1:8000/mekanik/pekerjaan`
- **Langkah Pengujian:**
  1. Mekanik otomatis diarahkan ke panel **Pekerjaan Saya** (`/mekanik/pekerjaan`).
  2. Buka tiket servis aktif kendaraan plat nomor `B 1234 ABC`.
  3. Amati informasi detail yang tampil:
     - Model kendaraan, tahun, nomor polisi, dan jarak tempuh KM.
     - Keluhan pelanggan (*Ganti oli mesin dan cek sistem rem*).
     - Rincian checklist tugas yang harus dikerjakan.
  4. Pada alur fisik bengkel: Mekanik memeriksa pekerjaan berdasarkan lembar Surat Perintah Kerja (SPK) yang dicetak Admin.

---

### 5.2 Halaman Rating & Evaluasi Mekanik Pribadi
- **URL:** `http://127.0.0.1:8000/mekanik/rating`
- **Langkah Pengujian:**
  1. Buka menu **Rating Saya** (`/mekanik/rating`).
  2. Periksa ringkasan statistik:
     - Skor bintang rata-rata kepuasan pelanggan.
     - Total ulasan yang masuk.
     - Daftar komentar dan review langsung dari customer.
- **Hasil yang Diharapkan:**
  - Menampilkan riwayat ulasan transparan khusus untuk performa Mekanik 1.

---

## Bagian 6: Role Admin (`admin@gmail.com`)

Keluar dari akun Mekanik, lalu login sebagai Administrator:
- **Email:** `admin@gmail.com`
- **Password:** `password`

### 6.1 Persetujuan Pembelian & Pemotongan Stok Warna Otomatis
- **URL:** `http://127.0.0.1:8000/admin/pengajuan`
- **Langkah Pengujian:**
  1. Buka pengajuan mobil *Nissan Kicks e-Power VL* berstatus *Menunggu Persetujuan*.
  2. Catat jumlah stok warna mobil saat ini (misal stok awal: 4 unit).
  3. Klik tombol **Setujui Pengajuan**.
  4. Buka tab baru ke menu Mobil (`/admin/mobil`) dan cari *Kicks e-Power VL*.
- **Hasil yang Diharapkan:**
  - Status pengajuan berubah menjadi **Disetujui** (menunggu pembayaran customer).
  - Stok unit mobil di database **otomatis berkurang 1 unit** (dari 4 menjadi 3).
  - Jika pengajuan dibatalkan/ditolak nantinya, stok unit otomatis kembali bertambah 1.

---

### 6.2 Verifikasi Bukti Pembayaran Customer
- **URL:** `http://127.0.0.1:8000/admin/pengajuan/{id}`
- **Langkah Pengujian:**
  *(Pastikan Customer telah mengunggah file bukti transfer pada Bagian 2.5)*:
  1. Buka halaman detail pengajuan pembelian di atas.
  2. Di bagian riwayat pembayaran, klik tombol **Lihat Bukti** untuk memeriksa file struk transfer yang diunggah customer.
  3. Klik tombol **Terima Pembayaran**.
  4. Ubah status pengajuan menjadi **Unit Disiapkan** -> **Unit Diserahkan** -> **Selesai**.
- **Hasil yang Diharapkan:**
  - Status pembayaran berubah menjadi **Lunas**.
  - Nilai omset penjualan unit mobil tercatat resmi ke laporan dealer.

---

### 6.3 Pengelolaan Suku Cadang Servis, Potong Stok, & Cetak SPK
- **URL:** `http://127.0.0.1:8000/admin/servis/{id}`
- **Langkah Pengujian Suku Cadang & Potong Stok Otomatis:**
  1. Buka detail servis kendaraan `B 1234 ABC`.
  2. Catat stok awal suku cadang *Filter Oli Mesin Genuine* di menu `/admin/spare-part` (misal stok awal: 15 pcs).
  3. Kembali ke detail servis, gulir ke kartu **Suku Cadang / Spare Part Diganti**:
     - Pilih Suku Cadang: **Filter Oli Mesin Genuine**.
     - Masukkan Jumlah (*Qty*): `2`.
     - Masukkan Catatan: *"Penggantian rutin servis berkala"*.
     - Klik tombol **Tambahkan Suku Cadang**.
  4. **Verifikasi Stok Berkurang:** Buka menu `/admin/spare-part` di tab baru.
     - **Hasil yang Diharapkan:** Stok *Filter Oli Mesin Genuine* **otomatis berkurang** dari 15 menjadi **13 pcs**.
  5. **Uji Pembatalan / Hapus Suku Cadang:**
     - Kembali ke detail servis, klik tombol **Hapus** (ikon sampah) pada suku cadang yang baru ditambahkan.
     - Buka kembali menu `/admin/spare-part`.
     - **Hasil yang Diharapkan:** Stok master suku cadang **otomatis bertambah kembali** menjadi **15 pcs**.
  6. Tambahkan kembali 1 pcs *Filter Oli Mesin* dan perbarui checklist tugas menjadi *Selesai*.
  7. Ubah status servis menjadi **Sedang Dikerjakan** (*In Progress*), lalu ubah menjadi **Selesai**.

- **Langkah Pengujian Cetak SPK Mekanik:**
  1. Pada halaman detail servis yang sama, klik tombol **Cetak SPK** (di samping nama mekanik) atau tombol **Cetak Nota Checklist** di bagian atas kartu pekerjaan.
  2. Tab baru terbuka mengarah ke URL `/admin/servis/{id}/cetak-spk`.
- **Hasil yang Diharapkan (Cetak SPK):**
  - Tampil dokumen format struk thermal 80mm lengkap berisi identitas bengkel JAF Dealer, nomor unik `SPK-SRV-XXXXXX`, data customer, nomor plat polisi, keluhan, checklist tugas servis, rincian suku cadang, kalkulasi total biaya jasa + part, serta kolom tanda tangan Mekanik dan Admin / SA.
  - Tombol hijau *Cetak Nota* memicu dialog print browser (`window.print()`).
  - Tampilan print bersih tanpa tombol navigasi web (`@media print`).

---

### 6.4 Uji Dropdown Mekanik Anti-Bentrok Jadwal
- **URL:** `http://127.0.0.1:8000/admin/servis`
- **Langkah Pengujian:**
  1. Buat / buka satu jadwal servis lain yang memiliki tanggal dan jam kedatangan yang persis sama dengan servis Mekanik 1 di atas.
  2. Buka dropdown **Pilih Mekanik**:
- **Hasil yang Diharapkan:**
  - Nama Mekanik 1 menampilkan label: **`Mekanik 1 (Bentrok jadwal jam ini)`** dan opsi berstatus **`disabled`** (tidak dapat dipilih).
  - Jika form dikirim secara paksa, sistem memvalidasi dan menolak penugasan demi mencegah bentrok kerja mekanik.

---

### 6.5 Moderasi Ulasan & Testimoni Pelanggan
- **URL:** `http://127.0.0.1:8000/admin/ulasan`
- **Langkah Pengujian:**
  1. Buka menu **Ulasan Pelanggan** (`/admin/ulasan`).
  2. Ulasan baru dari customer yang diajukan pada Bagian 2.5 akan tampil berstatus **Menunggu Moderasi**.
  3. Klik tombol centang hijau (**Setujui**).
  4. Buka halaman depan website (`http://127.0.0.1:8000/`).
- **Hasil yang Diharapkan:**
  - Status ulasan berubah menjadi *Disetujui*.
  - Ulasan pelanggan tersebut langsung tampil pada slider / grid testimoni di halaman Beranda.

---

### 6.6 Kelola Master Data & Manajemen Staf
- **Kelola Katalog Mobil (`/admin/mobil`):** Tambah unit, edit harga promo, aktifkan/nonaktifkan status unit mobil.
- **Kelola Galeri & Palet Warna (`/admin/mobil/{id}/gambar`):** Upload foto sudut warna baru, set foto utama (*primary*), dan atur stok per varian warna.
- **Kelola Akun Staf (`/admin/staf`):**
  1. Buka menu Staf.
  2. Klik tombol Ubah pada akun **Sales 2**.
  3. Ubah status aktif menjadi **Nonaktif** (*is_active = false*), lalu simpan.
  4. Coba lakukan login di jendela *Incognito* menggunakan akun `sales2@gmail.com`.
     - **Hasil yang Diharapkan:** Login ditolak dengan pesan: *"Akun Anda telah dinonaktifkan. Silakan hubungi administrator"*.
  5. Aktifkan kembali akun Sales 2 agar siap digunakan.

---

### 6.7 Pantau Widget Leaderboard di Dashboard Admin
- **URL:** `http://127.0.0.1:8000/admin/dashboard`
- **Langkah Pengujian:**
  1. Buka Dashboard Admin.
  2. Gulir ke bagian widget **Performa Tim Terbaik (Top Sales & Top Mekanik)**:
- **Hasil yang Diharapkan:**
  - **Kolom Kiri (Sales Terbaik):** Menampilkan akun Sales yang telah menyelesaikan transaksi pembelian dan test drive pada peringkat pertama dengan badge khusus **"Terbaik #1"**.
  - **Kolom Kanan (Mekanik Terbaik):** Menampilkan akun Mekanik 1 pada peringkat pertama dengan skor bintang 5.0 dan jumlah servis selesai.

---

## Bagian 7: Role Owner (`cruzgzz244@gmail.com`)

Keluar dari Admin, lalu login sebagai Owner:
- **Email:** `cruzgzz244@gmail.com`
- **Password:** `password`

### 7.1 Dashboard Eksekutif Bisnis & Tren Finansial
- **URL:** `http://127.0.0.1:8000/admin/dashboard`
- **Langkah Pengujian:**
  1. Buka Dashboard Owner.
  2. Amati metrik finansial eksekutif:
     - Kartu total **Omset Penjualan Unit Mobil** (Rupiah).
     - Kartu total **Pendapatan Jasa & Part Bengkel** (Rupiah).
     - Grafik tren performa 6 bulan terakhir.
     - Widget **Sales Terbaik** khusus Owner (dilengkapi nominal total nilai omset penjualan tiap sales).
- **Hasil yang Diharapkan:**
  - Data agregasi finansial tampil akurat sesuai transaksi yang baru saja diselesaikan.

---

### 7.2 Laporan Dealer & Ekspor Excel (.xlsx)
- **URL:** `http://127.0.0.1:8000/admin/laporan`
- **Langkah Pengujian:**
  1. Buka menu **Laporan**.
  2. Pilih filter periode: **Bulan Ini** (atau rentang tanggal kustom).
  3. Amati tabulasi laporan: ringkasan penjualan unit, unit display terjual, performa staf, suku cadang terlaris, dan rincian servis bengkel.
  4. Klik tombol hijau **Export Excel** di pojok kanan atas.
- **Hasil yang Diharapkan:**
  - File spreadsheet berekstensi **`.xlsx`** otomatis terunduh ke komputer dengan format tabel yang rapi, garis batas sel, dan nilai angka Rupiah terformat.

---

### 7.3 Audit Trail & Riwayat Aktivitas Sensitif (`activity_logs`)
- **URL:** `http://127.0.0.1:8000/admin/staf/aktivitas`
- **Langkah Pengujian:**
  1. Buka menu **Aktivitas Staf**.
  2. Periksa baris riwayat log yang tercatat:
     - Log aktivitas login staf.
     - Log persetujuan pengajuan pembelian oleh Admin.
     - Log penugasan sales dan mekanik.
     - Log perubahan status test drive dan servis.
- **Hasil yang Diharapkan:**
  - Setiap tindakan krusial tercatat lengkap beserta nama staf, jenis tindakan, waktu kejadian, dan alamat IP.

---

## Prosedur Mengembalikan Database ke Kondisi Bersih (Clean State)

Setelah Anda selesai melakukan seluruh simulasi pengujian manual di atas, kembalikan database ke kondisi bersih (*clean state* transaksi = 0) untuk persiapan presentasi menggunakan perintah berikut di PowerShell / Terminal aplikasi:

```powershell
php artisan tinker --execute="
DB::statement('SET FOREIGN_KEY_CHECKS = 0');
DB::table('payment_refunds')->truncate();
DB::table('payment_proofs')->truncate();
DB::table('testimonials')->truncate();
DB::table('purchase_requests')->truncate();
DB::table('mechanic_ratings')->truncate();
DB::table('service_booking_spare_parts')->truncate();
DB::table('service_booking_tasks')->truncate();
DB::table('service_bookings')->truncate();
DB::table('test_drives')->truncate();
DB::table('favorites')->truncate();
DB::table('notifications')->truncate();
DB::table('activity_logs')->truncate();
DB::table('password_reset_tokens')->truncate();
DB::table('sessions')->truncate();
DB::statement('SET FOREIGN_KEY_CHECKS = 1');
DB::table('cars')->where('id', 1)->update(['stock' => 4]);
DB::table('cars')->where('id', 4)->update(['stock' => 5]);
DB::table('cars')->where('id', 5)->update(['stock' => 6]);
"
php artisan cache:clear
```

> [!TIP]
> **Catatan Presentasi:**
> Skrip di atas hanya membersihkan riwayat transaksi percobaan tanpa menghapus data master (14 mobil, galeri foto, merek, kategori, layanan, suku cadang, dan akun staf tetap utuh 100%).

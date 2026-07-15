# Desain UI/UX Aplikasi Mobile Posyandu ML

## 1. Ringkasan Arah Desain

Dokumen ini menjadi pegangan desain untuk aplikasi Flutter Android Posyandu berbasis PRD "Aplikasi Mobile Posyandu Berbasis Machine Learning untuk Deteksi Dini Risiko Stunting". Fokus desain adalah membantu kader Posyandu dan Bidan/Ahli Gizi bekerja cepat, terstruktur, dan tetap empatik saat menangani data balita, skrining awal, rujukan, validasi, PMT, notifikasi, dan laporan.

Nama arah visual: **Ledger Posyandu**.

Karakter desain:

- Hangat-operasional: terasa seperti buku kerja lapangan yang rapi, bukan dashboard korporat steril.
- Android-native: memakai pola Material Design 3 sebagai dasar, tetapi tidak memakai tampilan template yang terlalu generik.
- Cepat dipindai: daftar, status, dan aksi utama harus terbaca dalam situasi Posyandu yang ramai.
- Etis dalam komunikasi risiko: hasil ML adalah skrining awal, bukan diagnosis.
- Padat namun manusiawi: layar Kader dibuat cepat dan jelas; layar Bidan dibuat lebih padat untuk triage dan administrasi.

Hal yang sengaja dihindari:

- Gradient hero, glassmorphism, blur dekoratif, neon accent, dan warna oversaturated.
- Card simetris besar di tengah layar sebagai pola default.
- Ikon generik tanpa label untuk aksi penting.
- Bahasa yang membuat orang tua merasa anaknya divonis.
- Tampilan klinis yang terlalu dingin untuk konteks layanan desa.

## 2. Prinsip Desain

### 2.1 Cepat untuk Kader

Kader sering bekerja sambil mencatat banyak balita dalam satu sesi. UI harus mengurangi beban ingatan:

- Aksi utama selalu dekat dengan konteks kerja.
- Input pengukuran hanya menonjolkan BB dan TB.
- Setelah data tersimpan, kader langsung bisa lanjut ke balita berikutnya.
- Error harus menjelaskan tindakan berikutnya, bukan hanya menyebut kegagalan.

### 2.2 Hati-hati untuk Bidan

Bidan/Ahli Gizi membutuhkan layar yang membantu triage:

- Rujukan disusun berdasarkan status dan tingkat perhatian.
- Data pengukuran, hasil skrining, dan riwayat keluarga tampil dalam satu alur baca.
- Keputusan validasi dibuat eksplisit: Observasi, Konseling, PMT, Rujuk Puskesmas, atau Cek ulang data.
- Catatan medis diberi ruang cukup, tetapi tidak membuat form terasa berat.

### 2.3 Tidak Memvonis Anak

Aplikasi tidak boleh memakai bahasa diagnosis untuk hasil ML. Gunakan bahasa preventif:

- Gunakan: "Risiko rendah", "Perlu perhatian", "Perlu ditinjau bidan".
- Hindari: "Anak stunting", "Diagnosis stunting", "Balita bermasalah".
- Jelaskan bahwa data akan ditinjau tenaga kesehatan untuk risiko sedang/tinggi.

### 2.4 Ledger, Bukan Poster

Tampilan utama harus seperti catatan operasional modern:

- Banyak memakai list row, section header, status strip, dan ringkasan kecil.
- Card dipakai untuk entitas penting seperti balita, rujukan, stok, atau laporan, bukan sebagai dekorasi.
- Spasi dibuat fungsional: cukup lega untuk disentuh, tetapi tidak membuang ruang.

## 3. Design Tokens

### 3.1 Color System - Light Theme

Palet dibuat rendah saturasi agar tidak terasa template AI atau terlalu klinis. Warna harus membantu tindakan dan prioritas, bukan menjadi dekorasi.

| Token | Hex | Penggunaan | Rationale |
|---|---:|---|---|
| `paper` | `#F8F4EC` | Background utama | Netral hangat seperti buku catatan kerja; lebih ramah dari putih murni. |
| `surface` | `#FFFDF8` | App bar, sheet, panel, input | Surface bersih namun tetap hangat. |
| `surfaceMuted` | `#EFE8DC` | Section band, disabled surface | Memisahkan area tanpa border keras. |
| `ink` | `#25231F` | Teks utama | Hitam hangat untuk keterbacaan tinggi. |
| `inkSoft` | `#5D594F` | Teks sekunder | Tetap jelas untuk label dan metadata. |
| `line` | `#DDD2C3` | Divider, border input | Garis seperti pembatas buku kerja, tidak terlalu kontras. |
| `primary` | `#4E6F5C` | CTA utama, nav aktif | Hijau daun redup: sehat, lokal, tidak neon. |
| `primaryPressed` | `#3F5B4A` | Pressed state | Menjaga feedback tanpa efek berlebihan. |
| `primarySoft` | `#DDE8DE` | Background badge/selected chip | Status aktif yang tenang. |
| `bidanBlue` | `#4F6F86` | Informasi Bidan, laporan | Biru tenang untuk keputusan profesional. |
| `bidanBlueSoft` | `#DDE8EE` | Tint informasi | Membantu membedakan area medis tanpa dingin berlebihan. |
| `attention` | `#9A6A2F` | Risiko sedang, stok menipis | Amber tanah, bukan kuning alarm yang membuat panik. |
| `attentionSoft` | `#F1E2C9` | Badge "Perlu perhatian" | Lembut namun tetap terlihat. |
| `review` | `#9A4E3A` | Risiko tinggi/tinjau bidan | Merah bata lembut untuk urgensi etis. |
| `reviewSoft` | `#F0D8D1` | Badge "Perlu ditinjau bidan" | Tidak menakut-nakuti, tetapi jelas prioritasnya. |
| `success` | `#587D4F` | Tersimpan, stok aman | Selaras dengan primary tetapi sedikit lebih cerah. |
| `warning` | `#9A6A2F` | Validasi dan stok minimum | Konsisten dengan perhatian. |
| `danger` | `#9A4E3A` | Error validasi, stok tidak cukup | Urgent tanpa merah tajam. |

Flutter seed recommendation:

```dart
const Color seedPrimary = Color(0xFF4E6F5C);
const Color seedSecondary = Color(0xFF4F6F86);
const Color seedTertiary = Color(0xFF9A6A2F);
```

### 3.2 Color System - Dark Theme Ringkas

Dark theme bukan desain utama, tetapi harus aman bila Android mengikuti system dark mode.

| Token | Hex | Catatan |
|---|---:|---|
| `paperDark` | `#181A17` | Background tidak hitam murni. |
| `surfaceDark` | `#20231F` | Surface dengan sedikit hijau hangat. |
| `surfaceMutedDark` | `#2A2F29` | Section dan selected chip. |
| `inkDark` | `#EEE8DD` | Teks utama. |
| `inkSoftDark` | `#C9C0B2` | Teks sekunder. |
| `lineDark` | `#3E463D` | Divider. |
| `primaryDark` | `#A9C8AD` | CTA dan active nav. |
| `attentionDark` | `#E4C084` | Risiko sedang. |
| `reviewDark` | `#E2A294` | Risiko tinggi. |
| `bidanBlueDark` | `#A9C5D8` | Informasi Bidan. |

Aturan dark theme:

- Jangan membalik warna risk badge menjadi terlalu terang penuh.
- Gunakan border dan tint gelap untuk status, dengan teks terang.
- Prioritaskan kontras teks minimal WCAG AA.

### 3.3 Typography

Gunakan font sistem Android/Flutter agar terasa native dan ringan. Hindari pairing display font yang tampak dekoratif. Kepribadian desain datang dari spacing, label, dan hierarki, bukan dari font eksotis.

Rekomendasi:

- Flutter default: `Roboto` atau `Noto Sans`.
- Jika ingin lebih humanis dan tersedia di project: `Noto Sans` untuk semua teks.
- Letter spacing tetap `0` kecuali label kecil yang butuh tracking minimal `0.2`.

| Style | Size | Weight | Line Height | Penggunaan |
|---|---:|---:|---:|---|
| `displaySmall` | 28 | 600 | 34 | Judul dashboard, hanya 1 per layar. |
| `titleLarge` | 22 | 600 | 28 | Header layar utama. |
| `titleMedium` | 18 | 600 | 24 | Judul section atau nama balita di detail. |
| `bodyLarge` | 16 | 400 | 24 | Isi utama, form, pesan dialog. |
| `bodyMedium` | 14 | 400 | 20 | List metadata dan helper text. |
| `labelLarge` | 14 | 600 | 20 | Tombol, chip penting. |
| `labelMedium` | 12 | 600 | 16 | Badge status, field label padat. |
| `caption` | 12 | 400 | 16 | Timestamp, catatan ringan. |

Aturan:

- Jangan memakai uppercase untuk banyak label; cukup untuk header kecil seperti "SESI HARI INI".
- Angka BB/TB boleh memakai `titleLarge` untuk mempercepat pembacaan.
- Tombol dengan teks panjang harus memakai 2 baris maksimal, bukan mengecilkan teks berlebihan.

### 3.4 Spacing, Shape, Elevation

Spacing memakai skala 4:

| Token | Nilai | Penggunaan |
|---|---:|---|
| `space2` | 2 | Nudge visual kecil. |
| `space4` | 4 | Jarak ikon-teks kecil. |
| `space8` | 8 | Padding komponen kecil. |
| `space12` | 12 | List row internal. |
| `space16` | 16 | Padding layar standar. |
| `space20` | 20 | Section gap. |
| `space24` | 24 | Header ke konten. |
| `space32` | 32 | Jarak antar kelompok besar. |

Shape:

- `radius4`: input inner, badge kecil.
- `radius8`: card/list container standar.
- `radius12`: bottom sheet dan dialog.
- Hindari rounded pill besar untuk semua elemen; gunakan pill hanya untuk chip/status yang memang kategorikal.

Elevation:

- Default flat dengan border `line`.
- App bar elevation `0`, beri divider bawah.
- Bottom sheet elevation rendah.
- Floating visual shadow hanya untuk FAB atau snackbar.

## 4. Navigation Model

### 4.1 Kader

Bottom navigation:

1. Beranda
2. Sesi
3. Balita
4. Skrining
5. Notifikasi

Aksi utama:

- Saat ada sesi aktif, tombol utama di dashboard: "Input Pengukuran".
- Di halaman Balita, FAB kecil atau button bawah: "Tambah Balita".
- Jangan menampilkan PMT, stok, laporan administratif, atau validasi medis untuk Kader.

### 4.2 Bidan/Ahli Gizi

Bottom navigation:

1. Beranda
2. Rujukan
3. PMT
4. Laporan
5. Notifikasi

Aksi utama:

- Di Rujukan: filter status dan search selalu terlihat.
- Di Detail Rujukan: CTA utama "Validasi".
- Di PMT: CTA "Tambah Barang" hanya di layar stok, bukan global FAB.

### 4.3 App Bar

Spesifikasi:

- Height: 64.
- Padding horizontal: 16.
- Title alignment: start.
- Right actions maksimal 2 ikon, masing-masing 48 tap target.
- Divider bawah 1 px `line`.

Contoh:

```text
[Back] Detail Rujukan                         [More]
----------------------------------------------------
```

## 5. Key User Flows dan Mockup

Wireframe di bawah adalah struktur layar, bukan pixel-perfect final. Implementasi Flutter harus menjaga prioritas informasi dan state yang dicatat.

### 5.1 Login dan Routing Role

Tujuan:

- Pengguna login dengan NIK/NIP dan password.
- Sistem mengarahkan ke dashboard sesuai role.
- Error kredensial mudah dipahami.

Struktur layar:

```text
Posyandu Desa
Catatan tumbuh kembang dan tindak lanjut balita

NIK / NIP
[________________]

Password
[________________] [eye]

[Masuk]

Butuh bantuan akun? Hubungi bidan/kader koordinator.
```

Spesifikasi:

- Background `paper`.
- Login panel bukan card mengambang besar; gunakan container penuh dengan padding 24 dan section divider tipis.
- Field NIK/NIP memakai keyboard numeric.
- Tombol "Masuk" full width, height 52, radius 8.
- Jika gagal: snackbar `dangerSoft` atau inline message di atas tombol.

Microcopy error:

- "NIK/NIP atau password belum sesuai. Periksa kembali lalu coba lagi."
- "Koneksi belum stabil. Data login belum bisa diperiksa."

### 5.2 Dashboard Kader

Tujuan:

- Kader langsung tahu apakah ada sesi aktif.
- Shortcut input pengukuran terlihat tanpa mencari menu.
- Jadwal dan ringkasan hari ini tetap ringkas.

Struktur layar:

```text
Selamat pagi, Bu Rini
Posyandu Melati 03

SESI HARI INI
Melati 03 - 18 Mei 2026
08.00-11.00 | Balai Desa
[Input Pengukuran]

Ringkasan
Dicatat 24   Perlu perhatian 3   Ditinjau bidan 1

Jadwal Berikutnya
19 Mei 2026 - Posyandu Melati 04

Catatan Kader
Gunakan bahasa "perlu diperhatikan" saat menjelaskan hasil.
```

Spesifikasi:

- Header memakai `titleLarge`, bukan hero besar.
- Sesi aktif memakai panel `surface` dengan border kiri 4 px `primary`.
- Ringkasan memakai compact stat row, bukan 3 card besar simetris.
- Catatan Kader memakai `attentionSoft` dengan ikon `info`.

State:

- Tidak ada sesi aktif: tampilkan jadwal terdekat dan tombol "Lihat Jadwal".
- Sesi selesai: tampilkan "Sesi hari ini selesai" dan ringkasan.

### 5.3 Alur Kader Saat Posyandu

Tujuan:

- Cari balita cepat.
- Tambah balita jika belum ada.
- Input BB/TB dan langsung lanjut.
- Cegah input ganda pada sesi yang sama.

#### 5.3.1 Cari Balita

```text
Sesi Melati 03
Cari nama balita, ibu, atau NIK
[__________________________________]

Sudah dicatat hari ini
Sari Wulandari        28 bln   [Tersimpan]

Belum dicatat
Raka Pratama          31 bln   [Input]
Nadia Putri           18 bln   [Input]

[+ Tambah Balita Baru]
```

Spesifikasi:

- Search field sticky di atas list.
- Row balita height 72.
- Status "Tersimpan" memakai badge `primarySoft`.
- Row disabled untuk balita yang sudah dicatat, tetapi tetap bisa dibuka detailnya.
- Jika search kosong: tampilkan "Ketik nama balita, nama ibu, atau NIK."

#### 5.3.2 Input Pengukuran

```text
Raka Pratama
31 bulan | Laki-laki | Ibu: Wulan

Berat badan (kg)
[  11.4  ]

Tinggi badan (cm)
[  87.5  ]

Tanggal ukur: otomatis hari ini
Kader: Bu Rini

[Simpan & Lanjut]
[Simpan saja]
```

Spesifikasi:

- BB dan TB memakai input numeric besar, height 56.
- Label satuan selalu terlihat, bukan placeholder saja.
- Tombol "Simpan & Lanjut" primary full width.
- "Simpan saja" secondary text button untuk kondisi kader ingin melihat detail.
- Setelah berhasil: snackbar "Pengukuran tersimpan. Prediksi diproses di belakang."

Error:

- BB/TB kosong: "Isi berat dan tinggi badan terlebih dahulu."
- Angka tidak realistis: "Periksa kembali angka BB/TB. Nilai terlihat di luar rentang wajar."
- Duplikasi: "Balita ini sudah dicatat pada sesi hari ini."

### 5.4 Hasil Skrining Hari Ini

Tujuan:

- Menampilkan hasil ML tanpa menghambat input kader.
- Menjelaskan status prediksi gagal.
- Risiko sedang/tinggi otomatis menjadi rujukan.

Struktur layar:

```text
Hasil Skrining Hari Ini
Melati 03 - 18 Mei 2026

[Semua] [Risiko rendah] [Perlu perhatian] [Ditinjau bidan] [Gagal]

Raka Pratama
TB 87.5 cm | BB 11.4 kg | 31 bulan
[Perlu perhatian]
Data sudah diteruskan untuk ditinjau bidan.

Sari Wulandari
[Risiko rendah]
Tetap lakukan pemantauan rutin.

Nadia Putri
[Prediksi gagal]
Pengukuran tersimpan. Coba proses ulang saat koneksi stabil.
```

Spesifikasi:

- Chip filter horizontal, height 36.
- Risk badge:
  - Risiko rendah: `primarySoft`, text `primaryPressed`.
  - Perlu perhatian: `attentionSoft`, text `attention`.
  - Perlu ditinjau bidan: `reviewSoft`, text `review`.
  - Prediksi gagal: `surfaceMuted`, text `inkSoft`, icon `refresh`.
- Jangan menampilkan probability sebagai angka besar ke Kader. Jika perlu, tampilkan di detail Bidan.

Microcopy:

- Risiko rendah: "Tetap lakukan pemantauan rutin."
- Perlu perhatian: "Pertumbuhan anak perlu diperhatikan. Data akan ditinjau tenaga kesehatan."
- Perlu ditinjau bidan: "Data diteruskan kepada Bidan/Ahli Gizi untuk pemeriksaan lebih lanjut."
- Prediksi gagal: "Pengukuran tersimpan. Prediksi dapat dicoba ulang."

### 5.5 Daftar dan Detail Rujukan Bidan

Tujuan:

- Bidan bisa memindai rujukan masuk dan mengambil keputusan.
- Data prediksi dan pengukuran menjadi bahan, bukan vonis.

#### 5.5.1 Daftar Rujukan

```text
Rujukan Masuk
[Cari balita atau nama ibu]

[Menunggu] [Divalidasi] [Cek ulang] [Selesai]

Hari ini
Raka Pratama        Melati 03
Perlu perhatian     31 bln | TB 87.5
[Validasi]

Dina Lestari        Melati 02
Perlu ditinjau bidan
[Validasi]
```

Spesifikasi:

- Bidan screens lebih padat: row height 76, metadata 2 baris.
- Prioritas sorting: `Perlu ditinjau bidan`, `Perlu perhatian`, lalu waktu terbaru.
- Search dan filter tetap di atas.
- Pagination selector tampil di bagian bawah list: 10, 25, 50, 100.

#### 5.5.2 Detail Rujukan

```text
Detail Rujukan

Raka Pratama
31 bulan | Laki-laki | Posyandu Melati 03
Ibu: Wulan | Penghasilan: Rp2.500.000 | Keluarga: 4

Pengukuran
BB 11.4 kg    TB 87.5 cm
Tanggal ukur 18 Mei 2026
Kader Bu Rini

Skrining awal
[Perlu perhatian]
Model random_forest_v1
Probabilitas: rendah 0.18 | sedang 0.62 | tinggi 0.20

Catatan komunikasi
Jangan sampaikan sebagai diagnosis. Gunakan "perlu diperhatikan".

[Validasi]
```

Spesifikasi:

- Detail memakai section dengan header kecil.
- Probability hanya tampil untuk Bidan.
- Catatan komunikasi selalu muncul untuk risiko sedang/tinggi.
- CTA "Validasi" sticky di bawah jika status `menunggu_validasi`.

### 5.6 Form Validasi Medis dan Tindak Lanjut

Tujuan:

- Bidan dapat memilih keputusan dan menyimpan catatan.
- Jika keputusan PMT, alur lanjut ke distribusi PMT.

Struktur layar:

```text
Validasi Medis
Raka Pratama

Keputusan
( ) Observasi
( ) Konseling
( ) PMT
( ) Rujuk Puskesmas
( ) Cek ulang data

Catatan bidan
[________________________________]
[________________________________]

[Simpan Validasi]
```

Spesifikasi:

- Gunakan radio list, bukan dropdown, karena pilihan hanya 5 dan perlu terlihat.
- Radio item height 52.
- Catatan minimal 2 baris, max 6 baris.
- Jika pilih PMT, setelah simpan arahkan ke "Distribusi PMT".
- Jika pilih Cek ulang data, notifikasi ke kader memakai pesan jelas.

Microcopy notifikasi:

- Validasi selesai: "Rujukan Raka sudah ditinjau bidan. Lihat arahan tindak lanjut."
- Cek ulang data: "Bidan meminta cek ulang data pengukuran Raka."

### 5.7 PMT dan Laporan

#### 5.7.1 Stok dan Distribusi PMT

```text
PMT
[Stok] [Distribusi]

Biskuit Balita
Stok 18 dus | Minimum 10
[Aman]

Susu UHT
Stok 7 kotak | Minimum 10
[Stok menipis]

[Tambah Barang]
```

Distribusi:

```text
Distribusi PMT
Balita: Raka Pratama
Dasar: Validasi PMT oleh Bidan

Barang PMT
[Pilih barang]

Jumlah
[ 2 ] dus

Keterangan
[________________]

[Simpan Distribusi]
```

Spesifikasi:

- Stok menipis memakai badge `attentionSoft`.
- Jika stok tidak cukup: inline error "Stok tidak cukup untuk jumlah ini."
- Setelah distribusi tersimpan: stok berkurang, notifikasi ke kader.

#### 5.7.2 Laporan PDF

```text
Laporan

Jenis laporan
[Prediksi Risiko]
[Kehadiran Posyandu]
[Distribusi PMT]

Rentang tanggal
[18 Mei 2026] - [18 Mei 2026]

[Download PDF]
```

Spesifikasi:

- Jenis laporan sebagai selectable list, bukan grid kartu besar.
- Date range picker mengikuti Material Android.
- Loading state saat generate: tombol berubah "Membuat PDF..."
- Error: "Laporan belum bisa dibuat. Periksa koneksi lalu coba lagi."

## 6. Component Specifications

### 6.1 Buttons

Primary button:

- Height: 52.
- Radius: 8.
- Padding horizontal: 16.
- Background: `primary`.
- Text: `labelLarge`, white.
- Disabled: background `surfaceMuted`, text `inkSoft`.
- Loading: spinner 18 + label.

Secondary button:

- Height: 48.
- Border: 1 px `line`.
- Background: `surface`.
- Text: `primary`.

Text button:

- Height: 44.
- Minimum tap target tetap 48 dengan padding luar.
- Dipakai untuk aksi sekunder seperti "Simpan saja".

Danger button:

- Background: `danger`.
- Hanya untuk tindakan destruktif seperti hapus data bila fitur aktif.

### 6.2 Text Fields

Default:

- Height: 56.
- Radius: 8.
- Border: 1 px `line`.
- Focus border: 2 px `primary`.
- Error border: 2 px `danger`.
- Label selalu di atas atau floating; jangan mengandalkan placeholder.

Measurement input:

- Font angka: `titleLarge`.
- Satuan kg/cm selalu sebagai suffix.
- Keyboard numeric decimal.
- Helper text maksimal 1 baris.

Search:

- Height: 48.
- Leading icon: search.
- Placeholder: "Cari nama balita, ibu, atau NIK".
- Clear icon muncul ketika ada input.

### 6.3 Chips dan Badges

Filter chip:

- Height: 36.
- Radius: 18.
- Padding: 12 horizontal.
- Selected: `primarySoft`, border `primary`.
- Unselected: `surface`, border `line`.

Risk badge:

- Height minimal: 28.
- Radius: 6.
- Padding: 8 horizontal.
- Text: `labelMedium`.
- Selalu pakai label empatik:
  - Risiko rendah
  - Perlu perhatian
  - Perlu ditinjau bidan
  - Prediksi gagal

### 6.4 List Row

Balita row:

- Height: 72.
- Padding: 16 horizontal, 10 vertical.
- Leading: avatar initial 40 atau icon anak sederhana.
- Title: nama balita.
- Subtitle: umur, jenis kelamin, nama ibu.
- Trailing: status/action.

Rujukan row:

- Height: 76.
- Title: nama balita.
- Metadata: Posyandu, umur, TB.
- Status badge selalu sebelum CTA agar prioritas terbaca.

Stok row:

- Height: 72.
- Title: nama barang.
- Subtitle: stok dan minimum.
- Badge: Aman/Stok menipis.

### 6.5 Cards dan Panels

Gunakan card hanya untuk unit informasi yang utuh:

- Sesi aktif.
- Detail balita.
- Detail rujukan.
- Ringkasan laporan.

Spesifikasi:

- Radius: 8.
- Border: 1 px `line`.
- Background: `surface`.
- Shadow: none.
- Padding: 16.

Status panel:

- Sama seperti card, tetapi punya border kiri 4 px sesuai status.
- Dipakai untuk sesi aktif, prediksi gagal, dan catatan komunikasi.

### 6.6 Navigation

Bottom navigation:

- Height: 72.
- Icon 24.
- Label `caption`.
- Active: `primary`.
- Inactive: `inkSoft`.
- Jangan memakai lebih dari 5 item.

Tabs:

- Height: 44.
- Indicator: underline 2 px `primary`.
- Untuk PMT Stok/Distribusi dan laporan bila diperlukan.

### 6.7 Dialog, Bottom Sheet, Snackbar

Dialog:

- Radius: 12.
- Width mengikuti Material.
- Title `titleMedium`.
- Body `bodyMedium`.
- Aksi kanan: primary, kiri: secondary/text.

Bottom sheet:

- Radius atas: 12.
- Handle kecil `line`.
- Dipakai untuk pilih barang PMT atau pagination bila list panjang.

Snackbar:

- Height dinamis, min 48.
- Radius: 8.
- Background berdasarkan state:
  - Success: `primaryPressed`.
  - Error: `danger`.
  - Info: `bidanBlue`.
- Teks singkat dan jelas.

### 6.8 Empty, Loading, Error States

Empty list:

- Gunakan ikon line sederhana dan teks pendek.
- Contoh Balita: "Belum ada data balita. Tambahkan data pertama untuk sesi ini."
- CTA jika relevan: "Tambah Balita".

Loading:

- Skeleton row untuk list.
- Button loading untuk simpan/generate PDF.
- Hindari full-screen spinner kecuali login awal.

Error:

- Inline untuk form.
- Snackbar untuk kegagalan aksi.
- Panel untuk prediksi gagal karena pengukuran tetap tersimpan.

## 7. Iconography

Gunakan ikon Material Symbols atau lucide-equivalent yang mudah dipahami. Hindari ikon ilustratif generik.

Mapping:

| Fungsi | Ikon |
|---|---|
| Beranda | home |
| Sesi | event_available |
| Balita | child_care atau face |
| Skrining | fact_check |
| Rujukan | assignment_late |
| PMT | inventory_2 |
| Laporan | description |
| Notifikasi | notifications |
| Search | search |
| Validasi | task_alt |
| Retry prediksi | refresh |
| Stok menipis | warning |

Aturan:

- Ikon penting selalu ditemani label.
- Ikon-only button wajib punya tooltip/semantics label.
- Jangan memakai ikon hati/medis berlebihan agar tidak terasa aplikasi klinik generik.

## 8. Bahasa dan Microcopy

### 8.1 Tone

Bahasa harus:

- Sederhana.
- Tidak menghakimi.
- Mengarahkan tindakan berikutnya.
- Cocok untuk Kader dan Bidan.

### 8.2 Istilah yang Dipakai

| Konteks | Pakai | Hindari |
|---|---|---|
| ML output rendah | Risiko rendah | Aman total |
| ML output sedang | Perlu perhatian | Hampir stunting |
| ML output tinggi | Perlu ditinjau bidan | Anak stunting |
| Validasi | Validasi medis | Diagnosis otomatis |
| PMT | Distribusi PMT | Bantuan karena bermasalah |
| Flask gagal | Prediksi gagal, pengukuran tersimpan | Data gagal total |

### 8.3 Pesan Penting

Hasil sedang:

```text
Pertumbuhan anak perlu diperhatikan. Data akan ditinjau tenaga kesehatan.
```

Hasil tinggi:

```text
Data diteruskan kepada Bidan/Ahli Gizi untuk pemeriksaan lebih lanjut.
```

Prediksi gagal:

```text
Pengukuran tersimpan. Prediksi dapat dicoba ulang saat koneksi stabil.
```

Duplikasi:

```text
Balita ini sudah dicatat pada sesi hari ini.
```

Stok tidak cukup:

```text
Stok tidak cukup untuk jumlah ini.
```

## 9. Android dan Flutter Implementation Notes

Theme:

- Gunakan `ColorScheme.fromSeed`, lalu override token yang perlu agar tidak menjadi palette Material default yang terlalu generik.
- Set `scaffoldBackgroundColor` ke `paper`.
- Set `cardTheme` flat dengan border, bukan shadow tinggi.
- Set `inputDecorationTheme` konsisten untuk semua field.

Accessibility:

- Minimum tap target 48 x 48.
- Kontras teks dicek pada risk badge dan disabled state.
- Semantics label untuk icon-only.
- Field BB/TB punya label dan satuan yang terbaca screen reader.

Responsiveness:

- Target utama Android phone 360-430 dp width.
- Padding horizontal 16 pada phone kecil, 20 pada phone besar.
- Bottom CTA sticky harus memberi safe area padding.
- Teks badge harus wrap atau truncate elegan; jangan mengecilkan font dengan viewport.

Performance:

- List balita, skrining, rujukan, dan stok harus berbasis pagination sesuai PRD.
- Search diproses backend, UI memberi debounce 300-500 ms.
- Prediksi ML tidak memblokir kader setelah pengukuran tersimpan.

## 10. QA Checklist Desain

### 10.1 PRD Coverage

- Login Kader/Bidan ada.
- Dashboard role ada.
- Balita search dan pagination ada.
- Sesi Posyandu dan input pengukuran ada.
- Status prediksi ML ada, termasuk gagal.
- Rujukan otomatis dan validasi Bidan ada.
- Distribusi PMT dan stok menipis ada.
- Notifikasi dan laporan PDF ada.
- Bahasa risiko tidak menjadi diagnosis.

### 10.2 Anti-AI-Pattern Checklist

- Tidak ada gradient hero.
- Tidak ada glassmorphism atau blur dekoratif.
- Tidak ada neon accent.
- Tidak semua konten diletakkan di card besar simetris.
- Warna punya alasan fungsi dan konteks.
- Komposisi layar mengikuti pekerjaan pengguna, bukan template landing page.
- Tipografi tidak memakai display font trendi.

### 10.3 Usability Scenarios

Kader saat ramai:

- Bisa membuka sesi aktif dari dashboard.
- Bisa mencari balita dengan cepat.
- Bisa input BB/TB dan menekan "Simpan & Lanjut".
- Bisa memahami bahwa prediksi diproses di belakang.

Bidan saat triage:

- Bisa melihat rujukan prioritas lebih dulu.
- Bisa membuka detail dan membaca data pengukuran.
- Bisa memilih keputusan validasi tanpa dropdown tersembunyi.
- Bisa lanjut ke PMT bila keputusan membutuhkan PMT.

Komunikasi risiko:

- Risiko sedang/tinggi tidak menyebut diagnosis.
- Ada catatan komunikasi untuk Kader/Bidan.
- Prediksi gagal tidak menghapus kepercayaan karena pengukuran tetap tersimpan.

### 10.4 Android QA Acceptance

- Tap target minimal 48.
- Tidak ada overflow teks pada tombol, badge, dan list row.
- Layout tetap rapi di 360 dp width.
- Keyboard numeric muncul untuk NIK, BB, TB, jumlah PMT.
- Snackbar tidak menutup CTA bawah terlalu lama.
- Dark theme token menjaga kontras dan tidak mengubah makna status.

## 11. Ringkasan Handoff

Implementasi Flutter sebaiknya dimulai dari token theme dan komponen kecil, lalu membangun journey utama:

1. Theme dan typography.
2. Shared components: button, field, chip, badge, list row, panel.
3. Auth dan role shell.
4. Kader flow: dashboard, sesi, cari balita, input pengukuran, skrining.
5. Bidan flow: rujukan, detail, validasi.
6. PMT, laporan, notifikasi.

Keputusan paling penting: UI ini bukan aplikasi diagnosis. Desain harus membantu pengguna mencatat, menyaring, merujuk, dan menindaklanjuti dengan bahasa yang tenang dan bertanggung jawab.

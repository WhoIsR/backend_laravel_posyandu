<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posyandu', function (Blueprint $table) {
            $table->id();
            $table->string('nama_posyandu');
            $table->text('alamat')->nullable();
            $table->string('desa')->nullable();
            $table->string('kecamatan')->nullable();
            $table->unsignedBigInteger('bidan_id')->nullable();
            $table->timestamps();
        });

        Schema::create('balita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posyandu_id')->constrained('posyandu');
            $table->string('nama_balita');
            $table->string('nik_balita')->nullable();
            $table->date('tanggal_lahir');
            $table->string('jenis_kelamin', 1);
            $table->string('nama_ibu');
            $table->string('nik_ibu')->nullable();
            $table->text('alamat');
            $table->unsignedBigInteger('penghasilan');
            $table->unsignedInteger('jumlah_keluarga');
            $table->timestamps();
        });

        Schema::create('katalog_pmt', function (Blueprint $table) {
            $table->id();
            $table->string('nama_barang');
            $table->string('jenis_barang');
            $table->string('satuan');
            $table->unsignedInteger('stok_saat_ini');
            $table->unsignedInteger('stok_minimum');
            $table->timestamps();
        });

        Schema::create('jadwal_posyandu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posyandu_id')->constrained('posyandu');
            $table->date('tanggal');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->string('lokasi');
            $table->text('keterangan')->nullable();
            $table->boolean('notif_h1_sent')->default(false);
            $table->timestamps();
        });

        Schema::create('sesi_posyandu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_posyandu_id')->nullable()->constrained('jadwal_posyandu');
            $table->foreignId('posyandu_id')->constrained('posyandu');
            $table->date('tanggal');
            $table->string('status')->default('berjalan');
            $table->foreignId('dibuka_oleh')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('pengukuran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_posyandu_id')->constrained('sesi_posyandu');
            $table->foreignId('balita_id')->constrained('balita');
            $table->foreignId('kader_id')->constrained('users');
            $table->date('tanggal_ukur');
            $table->decimal('berat_badan', 5, 2);
            $table->decimal('tinggi_badan', 5, 2);
            $table->string('status_prediksi')->default('menunggu');
            $table->timestamps();
            $table->unique(['sesi_posyandu_id', 'balita_id']);
        });

        Schema::create('hasil_prediksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengukuran_id')->constrained('pengukuran');
            $table->unsignedInteger('umur_bulan');
            $table->unsignedTinyInteger('jenis_kelamin_encoded');
            $table->decimal('tinggi_badan', 5, 2);
            $table->unsignedTinyInteger('kelompok_usia');
            $table->decimal('tb_per_bulan', 8, 4);
            $table->unsignedBigInteger('penghasilan');
            $table->unsignedInteger('jumlah_keluarga');
            $table->unsignedTinyInteger('predicted_class');
            $table->string('risk_level');
            $table->decimal('risk_score', 5, 4)->nullable();
            $table->json('probability_json');
            $table->string('model_version');
            $table->timestamps();
        });

        Schema::create('rujukan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('balita_id')->constrained('balita');
            $table->foreignId('pengukuran_id')->constrained('pengukuran');
            $table->foreignId('hasil_prediksi_id')->constrained('hasil_prediksi');
            $table->string('status_rujukan')->default('menunggu_validasi');
            $table->date('tanggal_rujukan');
            $table->timestamps();
        });

        Schema::create('validasi_medis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rujukan_id')->constrained('rujukan');
            $table->foreignId('bidan_id')->constrained('users');
            $table->string('keputusan');
            $table->text('catatan_bidan');
            $table->date('tanggal_validasi');
            $table->timestamps();
        });

        Schema::create('distribusi_pmt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('validasi_medis_id')->constrained('validasi_medis');
            $table->foreignId('balita_id')->constrained('balita');
            $table->foreignId('pmt_id')->constrained('katalog_pmt');
            $table->foreignId('bidan_id')->constrained('users');
            $table->unsignedInteger('jumlah');
            $table->date('tanggal_distribusi');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('judul');
            $table->text('pesan');
            $table->string('tipe');
            $table->json('data_json')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'notifikasi',
            'distribusi_pmt',
            'validasi_medis',
            'rujukan',
            'hasil_prediksi',
            'pengukuran',
            'sesi_posyandu',
            'jadwal_posyandu',
            'katalog_pmt',
            'balita',
            'posyandu',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

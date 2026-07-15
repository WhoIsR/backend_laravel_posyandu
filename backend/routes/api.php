<?php

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [ApiController::class, 'login'])->middleware('throttle:10,1');
Route::post('/analytics', [ApiController::class, 'storeAnalytics'])->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ApiController::class, 'logout']);
    Route::get('/me', [ApiController::class, 'me']);

    Route::get('/admin/users', [ApiController::class, 'adminListUsers']);
    Route::post('/admin/users', [ApiController::class, 'adminStoreUser']);
    Route::put('/admin/users/{id}', [ApiController::class, 'adminUpdateUser']);
    Route::get('/admin/posyandu', [ApiController::class, 'adminListPosyandu']);
    Route::post('/admin/posyandu', [ApiController::class, 'adminStorePosyandu']);
    Route::put('/admin/posyandu/{id}', [ApiController::class, 'adminUpdatePosyandu']);

    Route::get('/balita', [ApiController::class, 'listBalita']);
    Route::post('/balita', [ApiController::class, 'storeBalita']);
    Route::get('/balita/{id}', [ApiController::class, 'showBalita']);
    Route::put('/balita/{id}', [ApiController::class, 'updateBalita']);

    Route::get('/jadwal', [ApiController::class, 'listJadwal']);
    Route::post('/jadwal', [ApiController::class, 'storeJadwal']);
    Route::put('/jadwal/{id}', [ApiController::class, 'updateJadwal']);

    Route::post('/sesi', [ApiController::class, 'storeSesi']);
    Route::get('/sesi/aktif', [ApiController::class, 'sesiAktif']);
    Route::post('/sesi/{id}/selesai', [ApiController::class, 'closeSesi']);

    Route::post('/pengukuran', [ApiController::class, 'storePengukuran']);
    Route::get('/sesi/{id}/skrining', [ApiController::class, 'skrining']);
    Route::post('/pengukuran/{id}/retry-prediksi', [ApiController::class, 'retryPrediksi'])->middleware('throttle:6,1');

    Route::get('/rujukan', [ApiController::class, 'listRujukan']);
    Route::get('/rujukan/{id}', [ApiController::class, 'showRujukan']);
    Route::post('/rujukan/{id}/validasi', [ApiController::class, 'storeValidasi']);

    Route::get('/pmt', [ApiController::class, 'listPmt']);
    Route::post('/pmt', [ApiController::class, 'storePmt']);
    Route::put('/pmt/{id}', [ApiController::class, 'updatePmt']);
    Route::post('/distribusi-pmt', [ApiController::class, 'distribusiPmt']);

    Route::get('/notifikasi', [ApiController::class, 'listNotifikasi']);
    Route::post('/notifikasi/{id}/read', [ApiController::class, 'readNotifikasi']);
    Route::post('/fcm-token', [ApiController::class, 'updateFcmToken']);

    Route::get('/laporan/{type}', [ApiController::class, 'report'])
        ->whereIn('type', ['prediksi', 'kehadiran', 'distribusi-pmt', 'semua']);
});

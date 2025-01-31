<?php

use App\Http\Controllers\Api\AuthControllerApi;
use App\Http\Controllers\Api\KategoriControllerApi;
use App\Http\Controllers\Api\PekerjaControllerApi;
use App\Http\Controllers\Api\ProdukControllerApi;
use App\Http\Controllers\Api\TokoControllerApi;
use App\Http\Controllers\Api\DashboardControllerApi;
use App\Http\Controllers\Api\KartuStokControllerApi;
use App\Http\Controllers\Api\StokControllerApi;
use App\Http\Controllers\Api\TransaksiControllerApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use SebastianBergmann\CodeCoverage\Report\Html\Dashboard;

Route::post('register', [AuthControllerApi::class, 'register']);
Route::post('login', [AuthControllerApi::class, 'login']);
Route::post('verify-otp', [AuthControllerApi::class, 'verifyOtp']);
Route::post('forgot-password', [AuthControllerApi::class, 'forgotPassword']);
Route::post('reset-password', [AuthControllerApi::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthControllerApi::class, 'logout']);
    Route::get('listtokopemilik', [DashboardControllerApi::class, 'listtokobypemilik']);
    Route::get('dashboard', [DashboardControllerApi::class, 'index']);
    Route::get('kartustok/{id}', [StokControllerApi::class, 'show']);
    Route::get('produk/{id}/{bool}', [ProdukControllerApi::class, 'shows']);
    Route::get('riwayattransaksi/{id_toko}', [TransaksiControllerApi::class, 'riwayat']);
    Route::get('kartustok/{kodep}/{type}', [KartuStokControllerApi::class, 'shows']);
    Route::post('/s', [StokControllerApi::class, 'searchBykodepwitharray']);
    Route::get('/dashboardtoko/{idtoko}', [TokoControllerApi::class, 'dashboardtoko']);
    Route::post('/svopname', [StokControllerApi::class, 'addstokopname']);
    Route::post('/produk/{id}',[ProdukControllerApi::class, 'update']);
    Route::post('/toko/{id}',[TokoControllerApi::class, 'update']);
  
    // Route::apiResource('users', UserControllerApi::class)    ;
    // Route::apiResource('pemilik', PemilikControllerApi::class);
    Route::apiResource('toko', TokoControllerApi::class);
    Route::apiResource('pekerja', PekerjaControllerApi::class);
    Route::apiResource('kategori', KategoriControllerApi::class);
    Route::apiResource('produk', ProdukControllerApi::class);
    Route::apiResource('transaksi', TransaksiControllerApi::class);

    // Route::apiResource('transaksi', TransaksiControllerApi::class);
    // Route::apiResource('stok-opname', StokOpnameControllerApi::class);
    // Route::apiResource('kartu-stok', KartuStokControllerApi::class);
});

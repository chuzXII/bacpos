<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetailTransaksi;
use App\Models\DetailTransaksiPembelian;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\Toko;
use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\TransaksiPembelian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransaksiPembelianControllerApi extends Controller
{
    public function store(Request $request)
    {

        // Validasi input
        $validated = $request->validate([
            'id_toko' => 'required|integer|exists:toko,id_toko', // Pastikan id_toko ada di tabel tokos
            'id_user' => 'required|integer|exists:users,id_user',
            'items' => 'required|array',
            'items.*.kode_produk' => 'required|integer',
            'items.*.id_kategori' => '',
            'items.*.nama_produk' => '',
            'items.*.harga' => 'integer',
            'items.*.stok' => 'required|integer|min:1',
            'items.*.tipe' => 'required|string',
            'items.*.file' => 'nullable|image',
        ]);
        // if ($validated->fails()) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Validation errors',
        //         'errors' => $validated->errors()
        //     ], 422);
        // }
        // Generate ID transaksi
        $idTransaksi = 'TRXB-' . $validated['id_toko'] . now()->format('dmYHis') . rand(1000, 9999);

        // Mulai transaksi database
        DB::beginTransaction();

        try {
            // Simpan data transaksi
            $transaksi = TransaksiPembelian::create([
                'id_transaksi_pembelian' => $idTransaksi,
                'id_toko' => $validated['id_toko'],
                'id_user' => $validated['id_user'],
                'totalharga' => 0,
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);


            $totalHarga = 0;

            // Loop setiap item untuk menghitung harga dan menyimpan detail transaksi
            foreach ($validated['items'] as $item) {
                if ($item['tipe'] === 'manual') {
                    if (isset($item['file']) && $item['file']) {
                        // Handle the uploaded file
                        $file = $item['file']; // Assuming the 'url_img' field is part of the item
                        $fileName = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
                        $imagePath = $file->storeAs('uploadfile/produk', $fileName, 'public'); // Store it in the public directory
                    } else {
                        $imagePath = null; // No image uploaded
                    }
                    $produk = Produk::create([
                        'nama_produk' => $item['nama_produk'],
                        'id_toko' =>  $validated['id_toko'],
                        'harga' => $item['harga'],
                        'stok' => $item['stok'],
                        'is' => 0,
                        'kode_kategori' => $item['id_kategori'],
                        'url_img' => $imagePath
                    ]);
                    $stokAwal = $produk->stok;
                    $stokAkhir = $produk->stok + $item['stok'];

                    // Menambahkan ke tabel kartustok
                    KartuStok::create([
                        'kode_produk' => $produk->kode_produk,
                        'jenis_transaksi' => 'masuk', // Karena ini pengurangan stok
                        'tanggal' => now()->format('Y-m-d H:i:s'),
                        'jumlah' => $item['stok'],
                        'stok_awal' => $stokAwal,
                        'stok_akhir' => $stokAkhir,
                        'keterangan' => 'Transaksi penjualan, ID Transaksi: ' . $idTransaksi,
                    ]);
                } else {
                    // Find the product if tipe is 'existing'
                    $produk = Produk::findOrFail($item['kode_produk']);
                    $stokAwal = $produk->stok;
                    $stokAkhir = $produk->stok + $item['stok'];
                    $produk->stok = $stokAkhir;
                    $produk->save();
                    // Menambahkan ke tabel kartustok
                    KartuStok::create([
                        'kode_produk' => $produk->kode_produk,
                        'jenis_transaksi' => 'masuk', // Karena ini pengurangan stok
                        'tanggal' => now()->format('Y-m-d H:i:s'),
                        'jumlah' => $item['stok'],
                        'stok_awal' => $stokAwal,
                        'stok_akhir' => $stokAkhir,
                        'keterangan' => 'Transaksi penjualan, ID Transaksi: ' . $idTransaksi,
                    ]);
                }
                $harga = $produk->harga;
                $subtotal = $harga * $item['stok'];
                DetailTransaksiPembelian::create([
                    'id_transaksi_pembelian' => $idTransaksi,
                    'kode_produk' =>  $produk->kode_produk,
                    'harga' => $harga,
                    'qty' => $item['stok'],
                    'subtotal' => $subtotal,
                ]);
                $totalHarga += $subtotal;
            }

            $transaksi->update([
                'totalharga' => $totalHarga,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Checkout berhasil',
            ]);
        } catch (\Exception $e) {
            Log::error('Transaksi gagal: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => auth()->id_user ?? null, // Opsional, simpan ID user jika ada
            ]);
            // Rollback transaksi jika terjadi error
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan, transaksi dibatalkan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function riwayat($id_toko, Request $request)
    {
        // Validasi parameter StartDate dan EndDate
        $validated = $request->validate([
            'start_date' => 'nullable|date|before_or_equal:end_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        // Ambil parameter StartDate dan EndDate dari request
        $startDate = $validated['start_date'] ?? Carbon::now()->toDateString();
        $endDate = $validated['end_date'] ?? Carbon::now()->toDateString();


        // Query transaksi dengan filter tanggal jika diberikan
        $transaksiQuery = TransaksiPembelian::with('toko', 'user.pemilik', 'detailTransaksiPembelian.produk')
            ->where('id_toko', $id_toko);

        if ($startDate && $endDate) {
            $transaksiQuery->whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
        }

        $transaksi = $transaksiQuery->get();


        $transaksiGrouped = $transaksi->groupBy(function ($item) {
            return Carbon::parse($item->created_at)->format('Y-m-d'); // Group berdasarkan tanggal
        });

        // Menambahkan total per grup
        $transaksiWithSum = $transaksiGrouped->map(function (Collection $group) {
            $total = $group->sum(function ($item) {
                return $item->detailTransaksiPembelian->sum('harga'); // Asumsikan `harga` adalah total per detail transaksi
            });
            return [
                'total' => $total,
                'data' => $group,
            ];
        });

        return response()->json([
            'message' => 'Data transaksi berhasil diambil',
            'data' => $transaksiWithSum,
        ]);
    }
}

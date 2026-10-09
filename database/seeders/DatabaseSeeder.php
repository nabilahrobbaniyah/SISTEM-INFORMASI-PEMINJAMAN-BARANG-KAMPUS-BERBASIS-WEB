<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            ['Admin Kampus', 'admin@kampus.ac.id', 'admin', null],
            ['Petugas Inventaris', 'petugas@kampus.ac.id', 'petugas', 'P-0001'],
            ['Peminjam Contoh', 'peminjam@kampus.ac.id', 'peminjam', '103072400000'],
        ];

        foreach ($akun as [$name, $email, $role, $identity]) {
            User::forceCreate([
                'name' => $name,
                'email' => $email,
                'password' => 'password',
                'role' => $role,
                'identity_number' => $identity,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $elektronik = Category::create(['name' => 'Elektronik', 'description' => 'Perangkat elektronik kampus']);
        $lab = Category::create(['name' => 'Laboratorium', 'description' => 'Peralatan laboratorium']);
        $kabel = Category::create(['name' => 'Aksesori', 'description' => 'Kabel dan perlengkapan pendukung']);

        $barang = [
            [$elektronik, 'ELK-001', 'Proyektor Epson', 5, 'baik', 'Gudang Lantai 1'],
            [$elektronik, 'ELK-002', 'Kamera DSLR', 3, 'baik', 'Gudang Lantai 1'],
            [$lab, 'LAB-001', 'Mikroskop Digital', 4, 'rusak ringan', 'Laboratorium Dasar'],
            [$kabel, 'ACC-001', 'Kabel HDMI 5 meter', 10, 'baik', 'Gudang Lantai 1'],
        ];

        foreach ($barang as [$kategori, $kode, $nama, $jumlah, $kondisi, $lokasi]) {
            Item::create([
                'category_id' => $kategori->id,
                'item_code' => $kode,
                'name' => $nama,
                'total_quantity' => $jumlah,
                'available_quantity' => $jumlah,
                'condition' => $kondisi,
                'location' => $lokasi,
                'is_active' => true,
            ]);
        }

        $this->seedContohPeminjaman();
    }

    /** Dua data contoh agar dashboard dan alur peminjaman langsung bisa dicoba. */
    private function seedContohPeminjaman(): void
    {
        $peminjam = User::where('email', 'peminjam@kampus.test')->first();
        $petugas = User::where('email', 'petugas@kampus.test')->first();
        $proyektor = Item::where('item_code', 'ELK-001')->first();
        $kamera = Item::where('item_code', 'ELK-002')->first();

        // 1. Pengajuan yang masih menunggu persetujuan
        $menunggu = Loan::create([
            'loan_code' => 'PJM-CONTOH-01',
            'user_id' => $peminjam->id,
            'purpose' => 'Presentasi tugas kelompok',
            'loan_date' => now()->addDay()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'menunggu',
        ]);
        $menunggu->details()->create(['item_id' => $proyektor->id, 'quantity' => 1]);

        AppNotification::send(
            $petugas->id,
            'Pengajuan peminjaman baru',
            "{$peminjam->name} mengajukan peminjaman {$menunggu->loan_code}.",
            $menunggu->id
        );

        // 2. Peminjaman yang sedang berjalan (stok di tempat sudah berkurang)
        $berjalan = Loan::create([
            'loan_code' => 'PJM-CONTOH-02',
            'user_id' => $peminjam->id,
            'purpose' => 'Dokumentasi kegiatan himpunan',
            'loan_date' => now()->subDay()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'dipinjam',
            'processed_by' => $petugas->id,
            'processed_at' => now()->subDays(2),
            'handed_over_by' => $petugas->id,
            'handed_over_at' => now()->subDay(),
        ]);
        $berjalan->details()->create(['item_id' => $kamera->id, 'quantity' => 1]);
        $kamera->decrement('available_quantity', 1);
    }
}

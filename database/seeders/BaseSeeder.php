<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Minimum a fresh install needs: master data and one Admin account. */
class BaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Elektronik', 'Audio visual', 'Perlengkapan acara', 'Kabel dan aksesori'] as $name) {
            Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
        foreach (['Gudang Sarpras Lt. 1', 'Lab Multimedia', 'Ruang Organisasi'] as $name) {
            Location::firstOrCreate(['name' => $name]);
        }
        User::firstOrCreate(['email' => 'admin@silabar.id'], [
            'name' => 'Ratna Wulandari', 'password' => config('silabar.demo_password'), 'role' => Role::Admin->value,
            'nim' => 'NIP 198204112010', 'organization' => 'Biro Sarana Prasarana', 'trust_score' => 100, 'is_active' => true,
        ]);
        User::firstOrCreate(['email' => 'agus@silabar.id'], [
            'name' => 'Agus Santoso', 'password' => config('silabar.demo_password'), 'role' => Role::Petugas->value,
            'nim' => 'NIP 198807202014', 'organization' => 'Biro Sarana Prasarana', 'trust_score' => 100, 'is_active' => true,
        ]);
        User::firstOrCreate(['email' => 'rina@mhs.silabar.id'], [
            'name' => 'Rina Maharani', 'password' => config('silabar.demo_password'), 'role' => Role::Peminjam->value,
            'nim' => '2210101001', 'organization' => 'Himpunan Mahasiswa Informatika', 'trust_score' => 70, 'is_active' => true,
        ]);
    }
}

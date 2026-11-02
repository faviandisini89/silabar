<?php

namespace App\Support;

use App\Enums\Role;

class Nav
{
    /** @return list<array{0:?string,1:list<array{0:string,1:string,2:string}>}> [group title, [[route, label, icon]]] */
    public static function for(Role $role): array
    {
        $staff = ['Operasional', [
            ['staff.agenda', 'Agenda hari ini', 'cal'], ['staff.approvals', 'Persetujuan', 'inbox'], ['staff.counter', 'Loket', 'scan'],
            ['staff.inventory', 'Barang dan unit', 'box'], ['staff.maint', 'Perawatan', 'wrench'], ['staff.penalties', 'Denda', 'coin'], ['staff.reports', 'Laporan', 'dl'],
        ]];

        return match ($role) {
            Role::Peminjam => [[null, [['home', 'Beranda', 'home'], ['catalog', 'Katalog', 'grid'], ['loans.index', 'Tiket saya', 'ticket'], ['history', 'Riwayat dan denda', 'coin']]]],
            Role::Petugas => [$staff],
            Role::Admin => [['Analitik dan kendali', [
                ['admin.analytics', 'Dasbor analitik', 'chart'], ['admin.procure', 'Pengadaan', 'trend'], ['admin.users', 'Pengguna', 'users'],
                ['admin.settings', 'Pengaturan', 'gear'], ['admin.audit', 'Audit log', 'scroll'],
            ]], $staff],
        };
    }
}

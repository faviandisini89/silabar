<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role->value, 'is_active' => true]));
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/beranda')->assertRedirect('/masuk');
        $this->get('/admin/analitik')->assertRedirect('/masuk');
    }

    public function test_borrowers_cannot_reach_staff_or_admin_pages(): void
    {
        $this->as(Role::Peminjam)->get('/petugas/agenda')->assertForbidden();
        $this->get('/admin/pengguna')->assertForbidden();
    }

    public function test_staff_cannot_reach_admin_pages_but_admin_reaches_staff_pages(): void
    {
        $this->as(Role::Petugas)->get('/admin/audit')->assertForbidden();
        $this->as(Role::Admin)->get('/petugas/agenda')->assertOk();
    }

    public function test_deactivated_accounts_cannot_sign_in(): void
    {
        User::factory()->create(['email' => 'x@y.id', 'password' => 'rahasia123', 'role' => 'peminjam', 'is_active' => false]);
        $this->post('/masuk', ['email' => 'x@y.id', 'password' => 'rahasia123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_signing_in_lands_each_role_on_its_own_home(): void
    {
        foreach ([[Role::Peminjam, '/beranda'], [Role::Petugas, '/petugas/agenda'], [Role::Admin, '/admin/analitik']] as [$role, $home]) {
            $u = User::factory()->create(['password' => 'rahasia123', 'role' => $role->value, 'is_active' => true]);
            $this->post('/masuk', ['email' => $u->email, 'password' => 'rahasia123'])->assertRedirect($home);
            $this->post('/keluar');
        }
    }

    public function test_a_borrower_cannot_open_someone_elses_handover_document(): void
    {
        $this->as(Role::Peminjam)->get('/tiket/999/berita-acara')->assertNotFound();
    }
}

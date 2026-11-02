<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemManagementTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Location $location;
    private User $admin;
    private User $petugas;
    private User $peminjam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);
        $this->location = Location::create(['name' => 'Gudang Utama']);

        $this->admin = User::factory()->create([
            'email' => 'admin_test@silabar.id',
            'role' => Role::Admin->value,
            'is_active' => true,
        ]);

        $this->petugas = User::factory()->create([
            'email' => 'petugas_test@silabar.id',
            'role' => Role::Petugas->value,
            'is_active' => true,
        ]);

        $this->peminjam = User::factory()->create([
            'email' => 'peminjam_test@silabar.id',
            'role' => Role::Peminjam->value,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_add_unit_item_and_it_integrates_in_database(): void
    {
        $response = $this->actingAs($this->admin)->post('/petugas/barang', [
            'name' => 'Proyektor BenQ MX550',
            'sku' => 'BNQ',
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
            'tracking_type' => 'unit',
            'qty' => 3,
            'max_loan_days' => 5,
            'replacement_value' => 4500000,
            'description' => 'Proyektor untuk ruang rapat dan kelas.',
            'requires_approval' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'name' => 'Proyektor BenQ MX550',
            'sku' => 'BNQ',
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
            'tracking_type' => 'unit',
            'max_loan_days' => 5,
            'replacement_value' => 4500000,
            'description' => 'Proyektor untuk ruang rapat dan kelas.',
            'requires_approval' => 1,
        ]);

        $item = Item::where('sku', 'BNQ')->first();
        $this->assertNotNull($item);
        $this->assertCount(3, $item->units);
        $this->assertDatabaseHas('item_units', ['item_id' => $item->id, 'asset_code' => 'BNQ-001']);
        $this->assertDatabaseHas('item_units', ['item_id' => $item->id, 'asset_code' => 'BNQ-002']);
        $this->assertDatabaseHas('item_units', ['item_id' => $item->id, 'asset_code' => 'BNQ-003']);
    }

    public function test_admin_can_add_bulk_item_and_it_integrates_in_database(): void
    {
        $response = $this->actingAs($this->admin)->post('/petugas/barang', [
            'name' => 'Kabel HDMI 3m',
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
            'tracking_type' => 'bulk',
            'qty' => 25,
            'max_loan_days' => 3,
            'replacement_value' => 75000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'name' => 'Kabel HDMI 3m',
            'tracking_type' => 'bulk',
            'total_stock' => 25,
        ]);

        $item = Item::where('name', 'Kabel HDMI 3m')->first();
        $this->assertNotNull($item);
        $this->assertCount(0, $item->units);
        $this->assertEquals(25, $item->ownedCount());
    }

    public function test_petugas_cannot_add_item_and_gets_forbidden(): void
    {
        $response = $this->actingAs($this->petugas)->post('/petugas/barang', [
            'name' => 'Barang Ilegal Petugas',
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
            'tracking_type' => 'unit',
            'qty' => 1,
            'max_loan_days' => 3,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('items', ['name' => 'Barang Ilegal Petugas']);
    }

    public function test_peminjam_cannot_add_item_and_gets_forbidden(): void
    {
        $response = $this->actingAs($this->peminjam)->post('/petugas/barang', [
            'name' => 'Barang Ilegal Peminjam',
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
            'tracking_type' => 'unit',
            'qty' => 1,
            'max_loan_days' => 3,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('items', ['name' => 'Barang Ilegal Peminjam']);
    }

    public function test_guest_is_redirected_to_login_when_adding_item(): void
    {
        $response = $this->post('/petugas/barang', [
            'name' => 'Barang Guest',
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
            'tracking_type' => 'unit',
            'qty' => 1,
            'max_loan_days' => 3,
        ]);

        $response->assertRedirect('/masuk');
        $this->assertDatabaseMissing('items', ['name' => 'Barang Guest']);
    }
}

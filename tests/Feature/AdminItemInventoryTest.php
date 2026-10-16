<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryEvent;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminItemInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_specification_update_does_not_change_inventory_quantities(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $item = $this->createItem();

        $this->actingAs($admin)->put(route('admin.items.update', $item), [
            'category_id' => $item->category_id,
            'item_code' => $item->item_code,
            'name' => 'Proyektor baru',
            'total_quantity' => 5,
            'available_quantity' => 5,
            'condition' => 'baik',
            'location' => $item->location,
        ])->assertRedirect(route('admin.items.index'));

        $this->assertSame('Proyektor baru', $item->fresh()->name);
        $this->assertSame(5, $item->fresh()->total_quantity);
        $this->assertSame(3, $item->fresh()->available_quantity);
    }

    public function test_inventory_reduction_cannot_breach_active_or_approved_reservations(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $item = $this->createItem();
        $borrower = User::factory()->create();
        $loan = Loan::create([
            'loan_code' => 'PJM-ACTIVE-01',
            'user_id' => $borrower->id,
            'purpose' => 'Uji peminjaman aktif',
            'loan_date' => now()->subDay()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'dipinjam',
        ]);
        $loan->details()->create(['item_id' => $item->id, 'quantity' => 2]);
        $approvedLoan = Loan::create([
            'loan_code' => 'PJM-APPROVED-01',
            'user_id' => $borrower->id,
            'purpose' => 'Uji reservasi',
            'loan_date' => now()->addDay()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'status' => 'disetujui',
        ]);
        $approvedLoan->details()->create(['item_id' => $item->id, 'quantity' => 2]);

        $this->actingAs($admin)->post(route('admin.items.adjust-stock', $item), [
            'action' => 'pengurangan',
            'quantity' => 2,
            'reason' => 'Koreksi stok karena barang tidak ditemukan',
        ])->assertSessionHas('error');

        $this->assertSame(5, $item->fresh()->total_quantity);
        $this->assertSame(3, $item->fresh()->available_quantity);
    }

    public function test_inventory_correction_reconciles_the_serviceable_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $item = $this->createItem();

        $this->actingAs($admin)->post(route('admin.items.adjust-stock', $item), [
            'action' => 'koreksi',
            'quantity' => 3,
        ])->assertRedirect();

        $this->assertSame(3, $item->fresh()->total_quantity);
        $this->assertSame(3, $item->fresh()->available_quantity);
        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'stock_corrected',
            'quantity_before' => 5,
            'quantity_after' => 3,
        ]);
    }

    public function test_item_details_separate_actions_and_history_into_four_tabs(): void
    {
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $item = $this->createItem();
        $item->update([
            'light_damage_quantity' => 1,
            'repair_quantity' => 1,
            'lost_quantity' => 1,
            'inspection_quantity' => 1,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.items.show', $item))
            ->assertOk()
            ->assertSee('Identitas Barang')
            ->assertSee('Ringkasan Stok')
            ->assertSee('Tindakan')
            ->assertSee('Riwayat')
            ->assertSee('Total')
            ->assertSee('Rincian kondisi barang')
            ->assertSee('Laporkan kerusakan')
            ->assertSee('Barang ditemukan')
            ->assertSeeInOrder([
                'Laporkan kerusakan',
                'Barang ditemukan',
                'Mulai perbaikan barang rusak ringan',
            ])
            ->assertSee('class="col-12 collapse" id="report-damage-form"', false)
            ->assertSee('class="col-12 collapse show" id="found-item-actions"', false)
            ->assertSee('Pemeriksaan setelah perbaikan')
            ->assertSee('Hasil pemeriksaan')
            ->assertSee('Keterangan (opsional)')
            ->assertSee('Bukti foto (opsional)')
            ->assertDontSee('Cek stok untuk tanggal peminjaman')
            ->assertDontSee('Pindahkan barang dari stok siap di rak')
            ->assertDontSee('Barang berpindah ke pemeriksaan, bukan langsung ke stok tersedia.')
            ->assertDontSee('Perbaikan tidak mengubah total fisik.')
            ->assertDontSee('Total inventaris sebelum → sesudah')
            ->assertDontSee('unit tercatat, tidak termasuk yang dihapuskan')
            ->assertDontSee('Jumlah ini adalah stok layak pakai')
            ->assertDontSee('Gunakan bagian ini untuk mencatat barang masuk')
            ->assertDontSee('Catatan ini menunjukkan kapan stok berubah');

        $this->get(route('admin.items.inventory', $item))
            ->assertOk()
            ->assertSee('class="nav-link rounded-top active" id="tindakan-tab"', false);

        $this->get(route('admin.items.inventory', [$item, 'tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('class="nav-link rounded-top active" id="riwayat-tab"', false);

        $this->get(route('admin.items.show', [$item, 'tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('class="nav-link rounded-top active" id="riwayat-tab"', false)
            ->assertSee('Total inventaris')
            ->assertDontSee('Total inventaris sebelum → sesudah');
    }

    public function test_found_item_inspection_form_is_visible_after_recording_a_found_item(): void
    {
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $item = $this->createItem();
        $item->update(['lost_quantity' => 1]);

        $this->actingAs($staff)
            ->from(route('admin.items.show', [$item, 'tab' => 'tindakan']))
            ->followingRedirects()
            ->post(route('admin.items.found', $item), [
                'quantity' => 1,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertOk()
            ->assertSee('class="col-12 collapse show" id="found-item-actions"', false)
            ->assertSee('Hasil pemeriksaan')
            ->assertSee('Simpan Hasil Pemeriksaan');
    }

    public function test_heavy_damage_can_be_returned_to_shelf_or_moved_to_repair(): void
    {
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $item = $this->createItem();
        $item->update(['heavy_damage_quantity' => 2]);

        $this->actingAs($staff)
            ->post(route('admin.items.heavy-damage-follow-up', $item), [
                'action' => 'return_to_shelf',
                'quantity' => 1,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('success');

        $this->assertSame(6, $item->fresh()->total_quantity);
        $this->assertSame(4, $item->fresh()->available_quantity);
        $this->assertSame(1, $item->fresh()->heavy_damage_quantity);

        $this->actingAs($staff)
            ->post(route('admin.items.heavy-damage-follow-up', $item), [
                'action' => 'start_repair',
                'quantity' => 1,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, $item->fresh()->heavy_damage_quantity);
        $this->assertSame(1, $item->fresh()->repair_quantity);
        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'repair_started',
            'state_before' => 'rusak berat',
            'state_after' => 'perbaikan',
        ]);
    }

    public function test_heavy_damage_disposal_proposal_requires_admin_approval(): void
    {
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $item = $this->createItem();
        $item->update(['heavy_damage_quantity' => 2]);

        $this->actingAs($staff)
            ->get(route('admin.items.show', [$item, 'tab' => 'tindakan']))
            ->assertOk()
            ->assertSee('Tindak lanjut barang rusak berat')
            ->assertSee('Kembalikan ke rak')
            ->assertSee('Mulai perbaikan')
            ->assertSee('Usulkan penghapusan');

        $this->actingAs($staff)
            ->post(route('admin.items.heavy-damage-follow-up', $item), [
                'action' => 'propose_disposal',
                'quantity' => 1,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'description' => 'Tidak dapat diperbaiki',
            ])
            ->assertSessionHas('success');

        $this->assertSame(2, $item->fresh()->heavy_damage_quantity);
        $this->assertDatabaseHas('inventory_disposal_proposals', [
            'item_id' => $item->id,
            'quantity' => 1,
            'status' => 'pending',
        ]);

        $proposal = $item->disposalProposals()->firstOrFail();
        $this->actingAs($staff)
            ->post(route('admin.items.disposal-proposals.review', [$item, $proposal]), [
                'decision' => 'approve',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.items.disposal-proposals.review', [$item, $proposal]), [
                'decision' => 'approve',
                'review_note' => 'Penghapusan disetujui',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, $item->fresh()->heavy_damage_quantity);
        $this->assertSame(1, $item->fresh()->disposed_quantity);
        $this->assertDatabaseHas('inventory_disposal_proposals', [
            'id' => $proposal->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'stock_disposed',
            'state_before' => 'rusak berat',
            'state_after' => 'dihapuskan',
        ]);
    }

    public function test_damage_report_and_repair_can_be_submitted_without_description_or_evidence(): void
    {
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $item = $this->createItem();

        $this->actingAs($staff)
            ->post(route('admin.items.report-damage', $item), [
                'quantity' => 1,
                'condition' => 'rusak ringan',
                'occurred_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'damage_reported',
            'description' => '',
            'evidence_path' => null,
        ]);

        $item->update(['light_damage_quantity' => 1]);

        $this->actingAs($staff)
            ->post(route('admin.items.repair-start', $item), [
                'quantity' => 1,
                'started_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'repair_started',
            'description' => '',
            'evidence_path' => null,
        ]);
    }

    public function test_history_shows_total_inventory_change_as_a_signed_delta(): void
    {
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $item = $this->createItem();
        foreach ([[5, 3], [2, 5], [4, 4]] as [$before, $after]) {
            $item->inventoryEvents()->create([
                'user_id' => $staff->id,
                'action' => 'stock_corrected',
                'quantity' => abs($after - $before),
                'occurred_at' => now(),
                'description' => 'Uji perubahan inventaris',
                'quantity_before' => $before,
                'quantity_after' => $after,
            ]);
        }

        $this->actingAs($staff)
            ->get(route('admin.items.show', [$item, 'tab' => 'riwayat']))
            ->assertOk()
            ->assertSee('-2')
            ->assertSee('+3')
            ->assertSee('0');
    }

    public function test_action_descriptions_are_optional_for_found_inspection_and_repair_inspection(): void
    {
        $staff = User::factory()->create(['role' => 'petugas', 'is_active' => true]);
        $item = $this->createItem();
        $item->update([
            'lost_quantity' => 1,
            'inspection_quantity' => 1,
            'repair_quantity' => 1,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.items.found', $item), [
                'quantity' => 1,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'found_waiting_inspection',
            'description' => '',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.items.inspect-found', $item), [
                'quantity' => 1,
                'condition' => 'baik',
                'occurred_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('success');

        $this->actingAs($staff)
            ->post(route('admin.items.repair-complete', $item), [
                'quantity' => 1,
                'result' => 'layak',
                'completed_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'found_inspected',
            'description' => '',
        ]);
        $this->assertDatabaseHas('inventory_events', [
            'item_id' => $item->id,
            'action' => 'repair_completed',
            'description' => '',
        ]);
    }

    public function test_item_list_shows_number_of_problem_units_instead_of_generic_attention_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $item = $this->createItem();
        $item->update([
            'light_damage_quantity' => 1,
            'inspection_quantity' => 1,
            'repair_quantity' => 1,
            'heavy_damage_quantity' => 1,
            'lost_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.items.index'))
            ->assertOk()
            ->assertSee('5 bermasalah')
            ->assertDontSee('Perlu perhatian');
    }

    private function createItem(): Item
    {
        $category = Category::create(['name' => 'Peralatan']);

        return Item::create([
            'category_id' => $category->id,
            'item_code' => 'ITM-001',
            'name' => 'Proyektor',
            'total_quantity' => 5,
            'available_quantity' => 3,
            'condition' => 'baik',
            'location' => 'Gudang',
            'is_active' => true,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BranchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ambassadors_can_see_all_branches(): void
    {
        $firstAmbassador = User::factory()->create([
            'username' => 'ambassador.one',
            'role' => 'ambassador',
        ]);
        $secondAmbassador = User::factory()->create([
            'username' => 'ambassador.two',
            'role' => 'ambassador',
        ]);

        $this->createBranch($firstAmbassador->username);
        $this->createBranch($secondAmbassador->username);

        $response = $this->actingAs($firstAmbassador)->getJson('/api/branches');

        $response->assertOk()
            ->assertJsonCount(2, 'branches');
    }

    public function test_managers_can_see_all_branches(): void
    {
        $manager = User::factory()->create(['role' => 'service-desk-manager']);

        $this->createBranch('ambassador.one');
        $this->createBranch('ambassador.two');

        $response = $this->actingAs($manager)->getJson('/api/branches');

        $response->assertOk()->assertJsonCount(2, 'branches');
    }

    public function test_ambassador_can_only_update_and_delete_owned_branches(): void
    {
        $ambassador = User::factory()->create([
            'username' => 'ambassador.one',
            'role' => 'ambassador',
        ]);
        $otherBranch = $this->createBranch('ambassador.two');
        $payload = [
            'branch_name' => 'Updated branch',
            'branch_code' => 'BR-UPDATED',
            'branch_type' => 'Branch',
            'working_computers' => 12,
        ];

        $this->actingAs($ambassador)->putJson("/api/branches/{$otherBranch->id}", $payload)
            ->assertForbidden();
        $this->actingAs($ambassador)->deleteJson("/api/branches/{$otherBranch->id}")
            ->assertForbidden();
    }

    public function test_service_desk_manager_can_manage_every_branch(): void
    {
        $manager = User::factory()->create(['role' => 'service-desk-manager']);
        $branch = $this->createBranch('ambassador.one');
        $payload = [
            'branch_name' => 'Updated branch',
            'branch_code' => 'BR-UPDATED',
            'branch_type' => 'Branch',
            'working_computers' => 12,
        ];

        $this->actingAs($manager)->putJson("/api/branches/{$branch->id}", $payload)
            ->assertOk()
            ->assertJsonPath('branch.branch_name', 'Updated branch');
        $this->actingAs($manager)->deleteJson("/api/branches/{$branch->id}")
            ->assertOk();
    }

    public function test_branch_creation_provisions_a_private_branch_manager_account(): void
    {
        $ambassador = User::factory()->create([
            'username' => 'ambassador.one',
            'role' => 'ambassador',
        ]);

        $this->actingAs($ambassador)->postJson('/api/branches', [
            'branch_name' => 'Bole branch',
            'branch_code' => 'BOLE-01',
            'branch_type' => 'Branch',
            'working_computers' => 4,
        ])->assertCreated();

        $branchManager = User::query()->where('username', 'BOLE-01')->firstOrFail();
        $this->assertSame('branch-manager', $branchManager->role);
        $this->assertTrue(Hash::check('abc@123', $branchManager->password));
        $this->assertSame('Bole branch', $branchManager->branch->branch_name);

        $this->actingAs($ambassador)->getJson('/api/users')->assertForbidden();
    }

    public function test_ambassador_can_only_add_workbook_rows_to_owned_branches(): void
    {
        $ambassador = User::factory()->create([
            'username' => 'ambassador.one',
            'role' => 'ambassador',
        ]);
        $ownedBranch = $this->createBranch($ambassador->username);
        $otherBranch = $this->createBranch('ambassador.two');
        $data = [
            'branch_id' => $ownedBranch->id,
            'computer_name' => 'BRA1BOLEDSK026',
            'ip_address' => '10.1.0.6',
            'operating_system' => 'Windows 11',
            'status' => 'Active',
        ];

        $this->actingAs($ambassador)->postJson('/api/computer-workbook', $data)
            ->assertCreated()
            ->assertJsonPath('workbookRow.computer_name', 'BRA1BOLEDSK026');

        $this->actingAs($ambassador)->postJson('/api/computer-workbook', [
            ...$data,
            'branch_id' => $otherBranch->id,
        ])->assertNotFound();
    }

    private function createBranch(string $createdBy): Branch
    {
        return Branch::create([
            'branch_name' => 'Test branch',
            'branch_code' => uniqid('BR-', true),
            'branch_type' => 'Branch',
            'working_computers' => 10,
            'created_by' => $createdBy,
        ]);
    }
}

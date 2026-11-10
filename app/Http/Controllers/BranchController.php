<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branches = Branch::query()->latest()->get();

        // A branch manager sees their own branch in full. For every other branch,
        // the ambassador, backup ambassador, Kaspersky server IP and Display TV IP
        // are removed from the response; all other fields stay visible.
        if ($user?->role === 'branch-manager') {
            $branches->each(function (Branch $branch) use ($user) {
                if ((int) $branch->id !== (int) $user->branch_id) {
                    $branch->makeHidden([
                        'ambassador_name',
                        'ambassador_phone',
                        'backup_name',
                        'backup_phone',
                        'backup_username',
                        'kaspersky_server_ip',
                        'display_tv_ip',
                        'manager_can_edit',
                        'created_by',
                    ]);
                }
            });
        }

        return response()->json(['branches' => $branches]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanManage($request);

        $data = $this->validatedData($request);
        $creator = $request->user();
        $backup = ($data['backup_username'] ?? null) ? \App\Models\User::query()
            ->where('role', 'ambassador')
            ->where('username', $data['backup_username'])
            ->firstOrFail() : null;

        if ($creator->role === 'ambassador') {
            $data['ambassador_name'] = $creator->name;
            $data['ambassador_phone'] = $creator->phone;
        }
        $data['backup_name'] = $backup?->name;
        $data['backup_phone'] = $backup?->phone;

        $branch = DB::transaction(function () use ($data, $request) {
            $branch = Branch::create([
                ...$data,
                'created_by' => $request->user()->username,
            ]);
            $this->createBranchManager($branch);
            return $branch;
        });

        return response()->json(['branch' => $branch], 201);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->ensureOwnerOrManager($request, $branch);

        $data = $this->validatedData($request, $branch);
        if ($request->user()->role === 'ambassador') {
            $data['ambassador_name'] = $request->user()->name;
            $data['ambassador_phone'] = $request->user()->phone;
        }
        $backup = ($data['backup_username'] ?? null) ? \App\Models\User::query()
            ->where('role', 'ambassador')
            ->where('username', $data['backup_username'])
            ->firstOrFail() : null;
        $data['backup_name'] = $backup?->name;
        $data['backup_phone'] = $backup?->phone;
        DB::transaction(function () use ($branch, $data) {
            $branch->update($data);
            $this->syncBranchManager($branch);
        });

        return response()->json(['branch' => $branch->fresh()]);
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->ensureOwnerOrManager($request, $branch);
        User::query()
            ->where('branch_id', $branch->id)
            ->where('role', 'branch-manager')
            ->delete();
        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully.']);
    }

    // Turns on/off the branch manager's permission to update the Kaspersky
    // Net Agent and Endpoint details of this branch's computers.
    public function setManagerEdit(Request $request, Branch $branch): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->ensureOwnerOrManager($request, $branch);

        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        $branch->manager_can_edit = $data['enabled'];
        $branch->save();

        return response()->json(['branch' => $branch->fresh()]);
    }

    private function validatedData(Request $request, ?Branch $branch = null): array
    {
        $managerId = $branch
            ? User::query()->where('branch_id', $branch->id)->where('role', 'branch-manager')->value('id')
            : null;

        return $request->validate([
            'branch_name' => ['required', 'string', 'max:120'],
            'branch_code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('branches', 'branch_code')->ignore($branch?->id),
                Rule::unique('users', 'username')->ignore($managerId),
            ],
            'branch_type' => ['required', 'in:Branch,District,Head office'],
            'branch_location' => ['nullable', 'string', 'max:255'],
            'ambassador_name' => ['nullable', 'string', 'max:120'],
            'ambassador_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'backup_name' => ['nullable', 'string', 'max:120'],
            'backup_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'backup_username' => ['nullable', Rule::exists('users', 'username')->where(fn ($query) => $query->where('role', 'ambassador'))],
            'manager_name' => ['nullable', 'string', 'max:120'],
            'manager_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'assistant_name' => ['nullable', 'string', 'max:120'],
            'assistant_mobile' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'display_tv_ip' => ['nullable', 'ip'],
            'kaspersky_server_ip' => ['nullable', 'ip'],
            'working_computers' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function createBranchManager(Branch $branch): void
    {
        User::create([
            'name' => $branch->branch_name.' Branch Manager',
            'first_name' => mb_substr($branch->branch_name, 0, 80),
            'last_name' => 'Branch Manager',
            'username' => $branch->branch_code,
            'email' => $this->branchManagerEmail($branch->branch_code),
            'password' => 'abc@123',
            'role' => 'branch-manager',
            'branch_id' => $branch->id,
        ]);
    }

    private function syncBranchManager(Branch $branch): void
    {
        $manager = User::query()
            ->where('branch_id', $branch->id)
            ->where('role', 'branch-manager')
            ->first();

        if (! $manager) {
            $this->createBranchManager($branch);
            return;
        }

        $manager->update([
            'name' => $branch->branch_name.' Branch Manager',
            'first_name' => mb_substr($branch->branch_name, 0, 80),
            'username' => $branch->branch_code,
            'email' => $this->branchManagerEmail($branch->branch_code),
        ]);
    }

    private function branchManagerEmail(string $branchCode): string
    {
        return 'branch-'.$branchCode.'@branch.local';
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['ambassador', 'service-desk-manager'], true), 403, 'You do not have permission to manage branches.');
    }

    private function ensureOwnerOrManager(Request $request, Branch $branch): void
    {
        $isServiceDeskManager = $request->user()->role === 'service-desk-manager';
        abort_unless($isServiceDeskManager || $branch->created_by === $request->user()->username, 403, 'You can only manage branches you created.');
    }
}
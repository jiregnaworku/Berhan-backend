<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ComputerWorkbook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComputerWorkbookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ComputerWorkbook::query()->latest();

        if ($user->role === 'ambassador') {
            $query->where('created_by', $user->username);
        } elseif ($user->role === 'branch-manager') {
            // A branch manager only receives the computers of their own branch.
            $query->where('branch_id', $user->branch_id);
        }

        return response()->json(['workbookRows' => $query->get()]);
    }

    // Read-only: every computer in the bank, with its branch and owner details.
    // Used by the "Other ambassadors' computers" page. No write access is given here.
    public function all(Request $request): JsonResponse
    {
        abort_unless(
            in_array($request->user()?->role, ['ambassador', 'service-desk-manager'], true),
            403,
            'You do not have permission to view all computers.',
        );

        $rows = ComputerWorkbook::query()
            ->with('branch')
            ->latest()
            ->get()
            ->map(fn (ComputerWorkbook $row) => [
                'id' => $row->id,
                'branch_id' => $row->branch_id,
                'branch_name' => $row->branch?->branch_name,
                'branch_code' => $row->branch?->branch_code,
                'branch_location' => $row->branch?->branch_location,
                // The ambassador who owns the branch (falls back to who registered the row)
                'ambassador_username' => $row->branch?->created_by ?? $row->created_by,
                'ambassador_name' => $row->ambassador_name ?: $row->branch?->ambassador_name,
                'ip_address' => $row->ip_address,
                'computer_name' => $row->computer_name,
                'computer_job' => $row->computer_job,
                'operating_system' => $row->operating_system,
                'status' => $row->status,
                'network_agent' => $row->network_agent,
                'endpoint' => $row->endpoint_protection,
                'kaspersky_status' => $row->kaspersky_status,
            ]);

        return response()->json(['workbookRows' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanManage($request);

        $data = $this->validatedData($request);
        $branch = $this->ownedBranch(
            $request,
            $data['branch_id'],
            $request->user()->role === 'service-desk-manager',
        );

        if (isset($data['endpoint'])) {
            $data['endpoint_protection'] = $data['endpoint'];
            unset($data['endpoint']);
        }

        $row = ComputerWorkbook::create([
            ...$data,
            'created_by' => $request->user()->username,
            'ambassador_name' => $data['ambassador_name'] ?? $branch->ambassador_name,
        ]);

        return response()->json(['workbookRow' => $row], 201);
    }

    public function update(Request $request, ComputerWorkbook $computerWorkbook): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->ensureOwnerOrManager($request, $computerWorkbook);

        $data = $this->validatedData($request);
        $branch = $this->ownedBranch($request, $data['branch_id'], $request->user()->role === 'service-desk-manager');
        if (isset($data['endpoint'])) {
            $data['endpoint_protection'] = $data['endpoint'];
            unset($data['endpoint']);
        }

        $computerWorkbook->update([
            ...$data,
            'ambassador_name' => $data['ambassador_name'] ?? $branch->ambassador_name,
        ]);

        return response()->json(['workbookRow' => $computerWorkbook->fresh()]);
    }

    // Branch managers can update ONLY the Net Agent and Endpoint values of a
    // computer in their own branch, and only while the ambassador has turned
    // editing on for that branch.
    public function updateKaspersky(Request $request, ComputerWorkbook $computerWorkbook): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->role === 'branch-manager', 403, 'Only branch managers can use this action.');
        abort_unless(
            (int) $computerWorkbook->branch_id === (int) $user->branch_id,
            403,
            'You can only update computers of your own branch.',
        );

        $branch = Branch::query()->findOrFail($computerWorkbook->branch_id);
        abort_unless(
            $branch->manager_can_edit,
            403,
            'Editing is not enabled for your branch. Please ask your ambassador to turn it on.',
        );

        $data = $request->validate([
            'network_agent' => ['nullable', 'string', 'max:60'],
            'endpoint' => ['nullable', 'string', 'max:60'],
        ]);

        $computerWorkbook->update([
            'network_agent' => $data['network_agent'] ?? null,
            'endpoint_protection' => $data['endpoint'] ?? null,
        ]);

        return response()->json(['workbookRow' => $computerWorkbook->fresh()]);
    }

    public function destroy(Request $request, ComputerWorkbook $computerWorkbook): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->ensureOwnerOrManager($request, $computerWorkbook);
        $computerWorkbook->delete();

        return response()->json(['message' => 'Computer row deleted successfully.']);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'ambassador_name' => ['nullable', 'string', 'max:120'],
            'ip_address' => ['required', 'ip'],
            'computer_name' => ['nullable', 'string', 'max:120'],
            'computer_job' => ['nullable', 'in:MTO,Normal'],
            'operating_system' => ['required', 'in:Windows 10,Windows 11'],
            'status' => ['required', 'in:Active,Not active'],
            'network_agent' => ['nullable', 'string', 'max:60'],
            'endpoint_protection' => ['nullable', 'string', 'max:60'],
            'endpoint' => ['nullable', 'string', 'max:60'],
            'kaspersky_status' => ['nullable', 'in:Updated,Not updated'],
            'managed_kasp_ip' => ['nullable', 'string', 'max:60'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function ownedBranch(Request $request, int $branchId, bool $allBranches = false): Branch
    {
        $query = Branch::query()->whereKey($branchId);

        if (! $allBranches) {
            $query->where('created_by', $request->user()->username);
        }

        return $query->firstOrFail();
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['ambassador', 'service-desk-manager'], true), 403, 'You do not have permission to manage computer rows.');
    }

    private function ensureOwnerOrManager(Request $request, ComputerWorkbook $row): void
    {
        abort_unless(
            $request->user()->role === 'service-desk-manager'
                || $row->created_by === $request->user()->username,
            403,
            'You can only manage your own computer rows.',
        );
    }
}
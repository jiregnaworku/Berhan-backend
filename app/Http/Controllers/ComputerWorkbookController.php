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
        $query = ComputerWorkbook::query()->latest();

        if ($request->user()->role === 'ambassador') {
            $query->where('created_by', $request->user()->username);
        }

        return response()->json(['workbookRows' => $query->get()]);
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
        $computerWorkbook->update([
            ...$data,
            'ambassador_name' => $data['ambassador_name'] ?? $branch->ambassador_name,
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

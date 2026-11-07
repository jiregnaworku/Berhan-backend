<?php

namespace App\Http\Controllers;

use App\Models\ComputerWorkbook;
use App\Models\KasperskyStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KasperskyStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KasperskyStatus::query()->latest();

        if ($request->user()->role === 'ambassador') {
            $query->where('created_by', $request->user()->username);
        }

        return response()->json(['statuses' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request);
        $this->ownedComputer($request, $data['branch_id'], $data['computer_workbook_id']);

        $status = KasperskyStatus::create([
            ...$data,
            'created_by' => $request->user()->username,
        ]);

        return response()->json(['status' => $status], 201);
    }

    public function update(Request $request, KasperskyStatus $kasperskyStatus): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->ensureStatusAccess($request, $kasperskyStatus);

        $data = $this->validatedData($request);
        $this->ownedComputer($request, $data['branch_id'], $data['computer_workbook_id']);
        $kasperskyStatus->update($data);

        return response()->json(['status' => $kasperskyStatus->fresh()]);
    }

    public function destroy(Request $request, KasperskyStatus $kasperskyStatus): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->ensureStatusAccess($request, $kasperskyStatus);
        $kasperskyStatus->delete();

        return response()->json(['message' => 'Kaspersky status deleted successfully.']);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'computer_workbook_id' => ['required', 'integer', 'exists:computer_workbooks,id'],
            'network_agent' => ['required', 'ip'],
            'endpoint' => ['required', 'ip'],
            'status' => ['required', 'in:Updated,Not updated'],
        ]);
    }

    private function ownedComputer(Request $request, int $branchId, int $computerId): ComputerWorkbook
    {
        return ComputerWorkbook::query()
            ->whereKey($computerId)
            ->where('branch_id', $branchId)
            ->when(
                $request->user()->role === 'ambassador',
                fn ($query) => $query->where('created_by', $request->user()->username),
            )
            ->firstOrFail();
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, ['ambassador', 'service-desk-manager'], true),
            403,
            'You do not have permission to manage Kaspersky statuses.',
        );
    }

    private function ensureStatusAccess(Request $request, KasperskyStatus $status): void
    {
        abort_unless(
            $request->user()->role === 'service-desk-manager'
                || $status->created_by === $request->user()->username,
            403,
            'You can only manage your own Kaspersky statuses.',
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['branches' => Branch::query()->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAmbassador($request);

        $branch = Branch::create($this->validatedData($request));

        return response()->json(['branch' => $branch], 201);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $this->ensureAmbassador($request);

        $branch->update($this->validatedData($request));

        return response()->json(['branch' => $branch->fresh()]);
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        $this->ensureAmbassador($request);
        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully.']);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'branch_name' => ['required', 'string', 'max:120'],
            'branch_location' => ['nullable', 'string', 'max:255'],
            'ambassador_name' => ['nullable', 'string', 'max:120'],
            'ambassador_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'backup_name' => ['nullable', 'string', 'max:120'],
            'backup_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'manager_name' => ['nullable', 'string', 'max:120'],
            'manager_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'assistant_name' => ['nullable', 'string', 'max:120'],
            'assistant_mobile' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'display_tv_ip' => ['nullable', 'ip'],
            'kaspersky_server_ip' => ['nullable', 'ip'],
            'working_computers' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function ensureAmbassador(Request $request): void
    {
        abort_unless($request->user()?->role === 'ambassador', 403, 'Only ambassadors can manage branches.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\MtoBranchAccess;
use App\Models\MtoStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MtoStatusController extends Controller
{
    private const MTOS = ['RIA', 'Western Union', 'MoneyGram', 'Transfast', 'Dahabshiil'];

    private const STATUSES = ['Working', 'Not working', 'Do not have user', 'Unknown'];

    // Branches in the caller's scope + the reports of those branches.
    //  - ambassador: only the branches they own
    //  - branch manager: only their own branch
    //  - service desk manager: every branch
    public function index(Request $request): JsonResponse
    {
        $branches = $this->scopedBranches($request)->get();
        $branchIds = $branches->pluck('id');

        $enabled = MtoBranchAccess::query()
            ->whereIn('branch_id', $branchIds)
            ->where('enabled', true)
            ->pluck('branch_id')
            ->flip();

        $statuses = MtoStatus::query()
            ->whereIn('branch_id', $branchIds)
            ->latest()
            ->limit(1000)
            ->get();

        return response()->json([
            'branches' => $branches->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'branch_name' => $branch->branch_name,
                'branch_code' => $branch->branch_code,
                'created_by' => $branch->created_by,
                'mto_can_edit' => $enabled->has($branch->id),
            ])->values(),
            'mtoStatuses' => $statuses->map(fn (MtoStatus $row) => $this->statusArray($row))->values(),
        ]);
    }

    // A branch manager sends a report - only while editing is turned on for the branch.
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->role === 'branch-manager', 403, 'Only branch managers can submit an MTO status.');

        $data = $request->validate([
            'mto' => ['required', Rule::in(self::MTOS)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'remark' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = Branch::query()->findOrFail($user->branch_id);

        abort_unless(
            MtoBranchAccess::query()->where('branch_id', $branch->id)->where('enabled', true)->exists(),
            403,
            'MTO editing is not enabled for your branch. Please ask your ambassador to turn it on.',
        );

        $row = MtoStatus::create([
            ...$data,
            'branch_id' => $branch->id,
            'submitted_by' => $user->username,
            'review_state' => 'pending',
        ]);

        return response()->json(['mtoStatus' => $this->statusArray($row)], 201);
    }

    // The ambassador who owns the branch responds "accepted".
    public function accept(Request $request, MtoStatus $mtoStatus): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->role === 'ambassador', 403, 'Only ambassadors can respond to MTO reports.');

        $branch = Branch::query()->findOrFail($mtoStatus->branch_id);
        abort_unless($branch->created_by === $user->username, 403, 'You can only respond to reports of your own branches.');

        $mtoStatus->update([
            'review_state' => 'accepted',
            'accepted_by' => $user->username,
            'accepted_at' => now(),
        ]);

        return response()->json(['mtoStatus' => $this->statusArray($mtoStatus->fresh())]);
    }

    // Turn MTO editing on/off for ONE branch.
    public function setBranchAccess(Request $request, Branch $branch): JsonResponse
    {
        $user = $this->ensureCanToggle($request);

        abort_unless(
            $user->role === 'service-desk-manager' || $branch->created_by === $user->username,
            403,
            'You can only change MTO editing for your own branches.',
        );

        $enabled = (bool) $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        MtoBranchAccess::updateOrCreate(
            ['branch_id' => $branch->id],
            ['enabled' => $enabled, 'updated_by' => $user->username],
        );

        return response()->json(['branch_id' => $branch->id, 'mto_can_edit' => $enabled]);
    }

    // Turn MTO editing on/off for ALL branches in the caller's scope
    // (ambassador: all of their own, service desk manager: every branch).
    public function setAllAccess(Request $request): JsonResponse
    {
        $user = $this->ensureCanToggle($request);

        $enabled = (bool) $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        $branchIds = $this->scopedBranches($request)->pluck('id');

        $now = now();
        $rows = $branchIds->map(fn ($id) => [
            'branch_id' => $id,
            'enabled' => $enabled,
            'updated_by' => $user->username,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            MtoBranchAccess::upsert($chunk, ['branch_id'], ['enabled', 'updated_by', 'updated_at']);
        }

        return response()->json(['count' => count($rows), 'mto_can_edit' => $enabled]);
    }

    private function scopedBranches(Request $request): Builder
    {
        $user = $request->user();

        return match ($user?->role) {
            'ambassador' => Branch::query()->where('created_by', $user->username),
            'branch-manager' => Branch::query()->whereKey($user->branch_id),
            'service-desk-manager' => Branch::query(),
            default => abort(403, 'You do not have permission to use MTO status.'),
        };
    }

    private function ensureCanToggle(Request $request)
    {
        $user = $request->user();
        abort_unless(
            in_array($user?->role, ['ambassador', 'service-desk-manager'], true),
            403,
            'You do not have permission to change MTO editing.',
        );

        return $user;
    }

    private function statusArray(MtoStatus $row): array
    {
        return [
            'id' => $row->id,
            'branch_id' => $row->branch_id,
            'mto' => $row->mto,
            'status' => $row->status,
            'remark' => $row->remark,
            'submitted_by' => $row->submitted_by,
            'review_state' => $row->review_state,
            'accepted_by' => $row->accepted_by,
            'accepted_at' => $row->accepted_at,
            'created_at' => $row->created_at,
        ];
    }
}
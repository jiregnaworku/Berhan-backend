<?php

namespace App\Http\Controllers;

use App\Models\ItRelatedInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItRelatedInfoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'itRelatedInfos' => ItRelatedInfo::query()
                ->with('members')
                ->where('entry_type', 'category')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureManager($request);
        $data = $this->validatedData($request);
        if ($data['entry_type'] === 'member') {
            abort_unless(! empty($data['parent_id']) && ItRelatedInfo::query()->whereKey($data['parent_id'])->where('entry_type', 'category')->exists(), 422, 'A valid category is required for a member.');
        }
        $info = ItRelatedInfo::create($data);

        return response()->json(['itRelatedInfo' => $info], 201);
    }

    public function update(Request $request, ItRelatedInfo $itRelatedInfo): JsonResponse
    {
        $this->ensureManager($request);
        $data = $this->validatedData($request);
        if ($data['entry_type'] === 'member') {
            abort_unless(! empty($data['parent_id']) && ItRelatedInfo::query()->whereKey($data['parent_id'])->where('entry_type', 'category')->exists(), 422, 'A valid category is required for a member.');
        }
        $itRelatedInfo->update($data);

        return response()->json(['itRelatedInfo' => $itRelatedInfo->fresh()]);
    }

    public function destroy(Request $request, ItRelatedInfo $itRelatedInfo): JsonResponse
    {
        $this->ensureManager($request);
        $itRelatedInfo->delete();

        return response()->json(['message' => 'IT information deleted successfully.']);
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'entry_type' => ['required', 'in:category,member'],
            'parent_id' => ['nullable', 'integer', 'exists:it_related_infos,id'],
            'category' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required_if:entry_type,category', 'nullable', 'string', 'max:2000'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'team_phone' => ['nullable', 'string', 'max:30'],
            'office_phone' => ['required_if:entry_type,category', 'nullable', 'string', 'max:30'],
        ]);

        // The category column predates the category/member hierarchy and remains
        // non-null in existing deployments.
        if (empty($data['category'])) {
            $data['category'] = $data['title'];
        }
        if ($data['entry_type'] === 'member' && ! empty($data['parent_id'])) {
            $category = ItRelatedInfo::query()->whereKey($data['parent_id'])->where('entry_type', 'category')->first();
            if ($category) {
                $data['category'] = $category->title;
                $data['description'] = $category->description;
                $data['team_phone'] = $category->office_phone;
                $data['office_phone'] = $category->office_phone;
            }
        }

        return $data;
    }

    private function ensureManager(Request $request): void
    {
        abort_unless($request->user()?->role === 'service-desk-manager', 403, 'Only service desk managers can manage IT information.');
    }
}

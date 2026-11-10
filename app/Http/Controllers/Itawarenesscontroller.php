<?php

namespace App\Http\Controllers;

use App\Models\ItAwarenessRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItAwarenessController extends Controller
{
    private const RELATIONS = ['ambassador:id,name,first_name,last_name,username', 'branch:id,branch_name'];

    public function index(Request $request): JsonResponse
    {
        $this->ensureManager($request);

        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'day' => ['nullable', 'date_format:Y-m-d'],
            'year' => ['nullable', 'digits:4'],
        ]);

        $query = ItAwarenessRecord::query()
            ->with(self::RELATIONS)
            ->orderByDesc('awareness_date')
            ->orderByDesc('id');

        // One period at a time: day wins over month, month wins over year.
        if (! empty($filters['day'])) {
            $query->whereDate('awareness_date', $filters['day']);
        } elseif (! empty($filters['month'])) {
            [$year, $month] = explode('-', $filters['month']);
            $query->whereYear('awareness_date', $year)->whereMonth('awareness_date', $month);
        } elseif (! empty($filters['year'])) {
            $query->whereYear('awareness_date', $filters['year']);
        }

        return response()->json(['itAwarenessRecords' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureManager($request);
        $data = $this->validatedData($request, true);
        $file = $request->file('document');

        $record = ItAwarenessRecord::create([
            'ambassador_id' => $data['ambassador_id'],
            'branch_id' => $data['branch_id'],
            'awareness_date' => $data['awareness_date'],
            'document_name' => $file->getClientOriginalName(),
            'document_path' => $file->store('it-awareness'),
            'created_by' => $request->user()->username,
        ]);

        return response()->json(['itAwarenessRecord' => $record->load(self::RELATIONS)], 201);
    }

    public function update(Request $request, ItAwarenessRecord $record): JsonResponse
    {
        $this->ensureManager($request);
        $data = $this->validatedData($request, false);

        $attributes = [
            'ambassador_id' => $data['ambassador_id'],
            'branch_id' => $data['branch_id'],
            'awareness_date' => $data['awareness_date'],
        ];

        $oldPath = null;
        if ($file = $request->file('document')) {
            $oldPath = $record->document_path;
            $attributes['document_name'] = $file->getClientOriginalName();
            $attributes['document_path'] = $file->store('it-awareness');
        }

        $record->update($attributes);

        if ($oldPath) {
            Storage::delete($oldPath);
        }

        return response()->json(['itAwarenessRecord' => $record->fresh(self::RELATIONS)]);
    }

    public function destroy(Request $request, ItAwarenessRecord $record): JsonResponse
    {
        $this->ensureManager($request);

        Storage::delete($record->document_path);
        $record->delete();

        return response()->json(['message' => 'IT awareness record deleted successfully.']);
    }

    public function download(Request $request, ItAwarenessRecord $record): StreamedResponse
    {
        $this->ensureManager($request);
        abort_unless(Storage::exists($record->document_path), 404, 'The document could not be found.');

        return Storage::download($record->document_path, $record->document_name);
    }

    private function validatedData(Request $request, bool $requireDocument): array
    {
        return $request->validate([
            'ambassador_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'ambassador')],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'awareness_date' => ['required', 'date_format:Y-m-d'],
            'document' => [$requireDocument ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);
    }

    private function ensureManager(Request $request): void
    {
        abort_unless($request->user()?->role === 'service-desk-manager', 403, 'Only service desk managers can manage IT awareness records.');
    }
}
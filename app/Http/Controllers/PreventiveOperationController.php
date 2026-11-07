<?php

namespace App\Http\Controllers;

use App\Models\PreventiveOperation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PreventiveOperationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureManager($request);

        $operations = PreventiveOperation::query()
            ->with(['ambassador:id,name,first_name,last_name,username', 'branch:id,branch_name'])
            ->when($request->filled('month'), fn ($query) => $query->whereBetween('operation_date', [
                Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth()->toDateString(),
                Carbon::createFromFormat('Y-m', $request->string('month'))->endOfMonth()->toDateString(),
            ]))
            ->when($request->filled('day'), fn ($query) => $query->whereDate('operation_date', $request->string('day')))
            ->latest('operation_date')
            ->latest()
            ->get();

        return response()->json(['preventiveOperations' => $operations]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureManager($request);
        abort_if($request->has('document') && ! $request->hasFile('document'), 422, 'The scanned document could not be uploaded. Please try again with a smaller file.');
        $data = $request->validate([
            'ambassador_id' => ['required', 'integer', 'exists:users,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'operation_date' => ['required', 'date'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);
        $this->ensureAmbassador($data['ambassador_id']);
        $document = $data['document'];
        $path = $document->store('preventive-operations');
        unset($data['document']);
        $operation = PreventiveOperation::create([
            ...$data,
            'document_path' => $path,
            'document_name' => $document->getClientOriginalName(),
        ]);

        return response()->json(['preventiveOperation' => $operation->load(['ambassador:id,name,first_name,last_name,username', 'branch:id,branch_name'])], 201);
    }

    public function update(Request $request, PreventiveOperation $preventiveOperation): JsonResponse
    {
        $this->ensureManager($request);
        abort_if($request->has('document') && ! $request->hasFile('document'), 422, 'The scanned document could not be uploaded. Please try again with a smaller file.');
        $data = $request->validate([
            'ambassador_id' => ['required', 'integer', 'exists:users,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'operation_date' => ['required', 'date'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);
        $this->ensureAmbassador($data['ambassador_id']);
        if ($request->hasFile('document')) {
            Storage::delete($preventiveOperation->document_path);
            $data['document_path'] = $data['document']->store('preventive-operations');
            $data['document_name'] = $data['document']->getClientOriginalName();
        }
        unset($data['document']);
        $preventiveOperation->update($data);

        return response()->json(['preventiveOperation' => $preventiveOperation->fresh()->load(['ambassador:id,name,first_name,last_name,username', 'branch:id,branch_name'])]);
    }

    public function destroy(Request $request, PreventiveOperation $preventiveOperation): JsonResponse
    {
        $this->ensureManager($request);
        Storage::delete($preventiveOperation->document_path);
        $preventiveOperation->delete();

        return response()->json(['message' => 'Preventive operation deleted successfully.']);
    }

    public function download(Request $request, PreventiveOperation $preventiveOperation): StreamedResponse
    {
        $this->ensureManager($request);
        return Storage::download($preventiveOperation->document_path, $preventiveOperation->document_name);
    }

    private function ensureAmbassador(int $id): void
    {
        abort_unless(\App\Models\User::query()->whereKey($id)->where('role', 'ambassador')->exists(), 422, 'A valid ambassador is required.');
    }

    private function ensureManager(Request $request): void
    {
        abort_unless($request->user()?->role === 'service-desk-manager', 403, 'Only service desk managers can manage preventive operations.');
    }
}

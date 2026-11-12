<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchDirectory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

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

    // All branches from the master list (for the dropdown).
    public function directory(Request $request): JsonResponse
    {
        $this->ensureCanManage($request);

        $options = BranchDirectory::query()
            ->orderBy('name')
            ->get(['code', 'name', 'branch_type']);

        return response()->json(['branches' => $options]);
    }

    // Service desk manager adds a completely new branch to the master list.
    public function storeDirectory(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->role === 'service-desk-manager',
            403,
            'Only the service desk manager can add branches to the list.'
        );

        $request->merge([
            'code' => trim((string) $request->input('code')),
            'name' => trim((string) preg_replace('/\s+/', ' ', (string) $request->input('name'))),
        ]);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('branch_directory', 'code'),
                Rule::unique('users', 'username'),
            ],
            'name' => ['required', 'string', 'max:120', Rule::unique('branch_directory', 'name')],
            'branch_type' => ['required', 'in:Branch,District,Head office'],
        ], [
            'code.unique' => 'This branch code is already in use.',
            'code.regex' => 'The branch code may only contain letters, numbers, dots, dashes and underscores.',
            'name.unique' => 'A branch with this name already exists in the list.',
        ]);

        $entry = BranchDirectory::create($data);

        return response()->json([
            'branch' => $entry->only(['code', 'name', 'branch_type']),
        ], 201);
    }

    // Service desk manager removes an unused entry from the master list.
    public function destroyDirectory(Request $request, string $code): JsonResponse
    {
        abort_unless(
            $request->user()?->role === 'service-desk-manager',
            403,
            'Only the service desk manager can remove branches from the list.'
        );

        $entry = BranchDirectory::query()->where('code', $code)->firstOrFail();

        $inUse = Branch::query()->where('branch_code', $entry->code)->first();

        if ($inUse) {
            $first = $this->assignedFirstName($inUse);
            $message = $first !== ''
                ? sprintf('The "%s" is assigned to "%s", so it cannot be removed from the list. Delete the branch first.', $entry->name, $first)
                : sprintf('The "%s" has already been added as a branch, so it cannot be removed from the list. Delete the branch first.', $entry->name);

            return response()->json(['message' => $message], 422);
        }

        $entry->delete();

        return response()->json(['message' => 'Branch removed from the list.']);
    }
    
    // Service desk manager edits the name, code or type of a branch in the master list.
// If the branch is already added, its record and branch manager login follow the
// change; the ambassador and every other detail stay exactly as they are.
public function updateDirectory(Request $request, string $code): JsonResponse
{
    abort_unless(
        $request->user()?->role === 'service-desk-manager',
        403,
        'Only the service desk manager can edit the branch list.'
    );

    $entry = BranchDirectory::query()->where('code', $code)->firstOrFail();
    $branch = Branch::query()->where('branch_code', $entry->code)->first();
    $managerId = $branch
        ? User::query()->where('branch_id', $branch->id)->where('role', 'branch-manager')->value('id')
        : null;

    $request->merge([
        'code' => trim((string) $request->input('code')),
        'name' => trim((string) preg_replace('/\s+/', ' ', (string) $request->input('name'))),
    ]);

    $data = $request->validate([
        'code' => [
            'required',
            'string',
            'max:10',
            'regex:/^[A-Za-z0-9._-]+$/',
            Rule::unique('branch_directory', 'code')->ignore($entry->id),
            Rule::unique('branches', 'branch_code')->ignore($branch?->id),
            Rule::unique('users', 'username')->ignore($managerId),
        ],
        'name' => ['required', 'string', 'max:120', Rule::unique('branch_directory', 'name')->ignore($entry->id)],
        'branch_type' => ['required', 'in:Branch,District,Head office'],
    ], [
        'code.unique' => 'This branch code is already in use.',
        'code.regex' => 'The branch code may only contain letters, numbers, dots, dashes and underscores.',
        'name.unique' => 'A branch with this name already exists in the list.',
    ]);

    DB::transaction(function () use ($entry, $branch, $data) {
        $entry->update($data);

        if ($branch) {
            $branch->update([
                'branch_code' => $data['code'],
                'branch_name' => $data['name'],
                'branch_type' => $data['branch_type'],
            ]);
            // Keeps the branch manager's name, username (= branch code) and email in step.
            $this->syncBranchManager($branch);
        }
    });

    return response()->json([
        'entry' => $entry->fresh()->only(['code', 'name', 'branch_type']),
        'branch' => $branch?->fresh(),
    ]);
}

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanManage($request);

        // If this branch was already added, say who it is assigned to.
        $existing = Branch::query()
            ->where('branch_code', (string) $request->input('branch_code'))
            ->first();

        if ($existing) {
            $first = $this->assignedFirstName($existing);
            $message = $first !== ''
                ? sprintf('The "%s" is assigned to "%s".', $existing->branch_name, $first)
                : sprintf('The "%s" has already been added.', $existing->branch_name);

            return response()->json([
                'message' => $message,
                'errors' => ['branch_code' => [$message]],
            ], 422);
        }

        $data = $this->validatedData($request);

        // Name and type always come from the master list, never from the request.
        $entry = BranchDirectory::query()->where('code', $data['branch_code'])->firstOrFail();
        $data['branch_code'] = $entry->code;
        $data['branch_name'] = $entry->name;
        $data['branch_type'] = $entry->branch_type;

        $creator = $request->user();

        // Service desk manager may choose the ambassador; the branch then belongs to them.
        $createdBy = $this->applyChosenAmbassador($request, $data) ?? $creator->username;

        $backup = ($data['backup_username'] ?? null) ? User::query()
            ->where('role', 'ambassador')
            ->where('username', $data['backup_username'])
            ->firstOrFail() : null;

        if ($creator->role === 'ambassador') {
            $data['ambassador_name'] = $creator->name;
            $data['ambassador_phone'] = $creator->phone;
        }
        $data['backup_name'] = $backup?->name;
        $data['backup_phone'] = $backup?->phone;

        $branch = DB::transaction(function () use ($data, $createdBy) {
            $branch = Branch::create([
                ...$data,
                'created_by' => $createdBy,
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

        // branch_code, branch_name and branch_type are not validated here,
        // so they are never changed by an update.
        $data = $this->validatedData($request, $branch);

        // Service desk manager can move the branch to another ambassador.
        $newOwner = $this->applyChosenAmbassador($request, $data);
        if ($newOwner) {
            $data['created_by'] = $newOwner;
        }

        if ($request->user()->role === 'ambassador') {
            $data['ambassador_name'] = $request->user()->name;
            $data['ambassador_phone'] = $request->user()->phone;
        }
        $backup = ($data['backup_username'] ?? null) ? User::query()
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
        $rules = [
            'branch_location' => ['nullable', 'string', 'max:255'],
            'ambassador_name' => ['nullable', 'string', 'max:120'],
            'ambassador_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'ambassador_username' => ['nullable', Rule::exists('users', 'username')->where(fn ($query) => $query->where('role', 'ambassador'))],
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
        ];

        // Only when creating: the code must come from the master list.
        // (An already-added branch is caught earlier in store() with a friendlier message.)
        if ($branch === null) {
            $rules['branch_code'] = [
                'required',
                'string',
                'max:30',
                Rule::exists('branch_directory', 'code'),
                Rule::unique('users', 'username'),
            ];
        }

        return $request->validate($rules);
    }

    // Applies the ambassador chosen by the service desk manager.
    // Always removes ambassador_username from $data (it is not a branches column).
    // Returns the username that should now own the branch (created_by),
    // or null when the owner should stay as it is.
    private function applyChosenAmbassador(Request $request, array &$data): ?string
    {
        $username = $data['ambassador_username'] ?? null;
        unset($data['ambassador_username']);

        if ($request->user()->role !== 'service-desk-manager') {
            return null;
        }

        if ($username) {
            $ambassador = User::query()
                ->where('role', 'ambassador')
                ->where('username', $username)
                ->firstOrFail();

            $data['ambassador_name'] = $ambassador->name;
            $data['ambassador_phone'] = $ambassador->phone;

            return $ambassador->username;
        }

        // Ambassador cleared: the service desk manager keeps the branch.
        return blank($data['ambassador_name'] ?? null) ? $request->user()->username : null;
    }

    // First name of the ambassador this branch is assigned to.
    private function assignedFirstName(Branch $branch): string
    {
        $fullName = trim((string) $branch->ambassador_name);

        if ($fullName === '') {
            return '';
        }

        $owner = User::query()
            ->where('role', 'ambassador')
            ->where('name', $fullName)
            ->first();

        return $owner?->first_name ?: Str::before($fullName, ' ');
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
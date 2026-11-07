<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureServiceDeskManager($request);

        return response()->json([
            'users' => User::query()->with('branch:id,branch_name')->latest()->get(),
        ]);
    }

    public function ambassadors(): JsonResponse
    {
        return response()->json([
            'ambassadors' => User::query()
                ->where('role', 'ambassador')
                ->orderBy('first_name')
                ->get(['id', 'name', 'first_name', 'last_name', 'username', 'phone']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureServiceDeskManager($request);

        $role = $request->input('role');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'id_number' => ['required', 'string', 'max:80', 'unique:users,id_number'],
            'role' => ['required', 'in:ambassador,service-desk-manager,branch-manager'],
            'branch_code' => ['required_if:role,branch-manager', 'nullable', 'string', 'max:30', 'regex:/^[0-9]+$/', 'unique:users,username', 'unique:branches,branch_code'],
            'branch_name' => ['required_if:role,branch-manager', 'nullable', 'string', 'max:120'],
            'manager_name' => ['required_if:role,branch-manager', 'nullable', 'string', 'max:120'],
            'manager_phone' => ['required_if:role,branch-manager', 'nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'assistant_name' => ['nullable', 'string', 'max:120'],
            'assistant_mobile' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'ambassador' => ['nullable', Rule::exists('users', 'username')->where(fn ($query) => $query->where('role', 'ambassador'))],
            'backup_ambassador' => ['nullable', Rule::exists('users', 'username')->where(fn ($query) => $query->where('role', 'ambassador'))],
            'backup_ambassador_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
        ]);
        if ($role === 'branch-manager') {
            return DB::transaction(function () use ($request, $data) {
                $ambassadorUser = $data['ambassador'] ? User::query()->where('username', $data['ambassador'])->first() : null;
                $backupAmbassadorUser = $data['backup_ambassador'] ? User::query()->where('username', $data['backup_ambassador'])->first() : null;

                $branch = Branch::create([
                    'branch_code' => $data['branch_code'],
                    'branch_name' => $data['branch_name'],
                    'branch_type' => 'Branch',
                    'manager_name' => $data['manager_name'],
                    'manager_phone' => $data['manager_phone'],
                    'assistant_name' => $data['assistant_name'] ?? null,
                    'assistant_mobile' => $data['assistant_mobile'] ?? null,
                    'ambassador_name' => $ambassadorUser?->name,
                    'ambassador_phone' => $ambassadorUser?->phone,
                    'backup_username' => $data['backup_ambassador'] ?? null,
                    'backup_name' => $backupAmbassadorUser?->name,
                    'backup_phone' => $data['backup_ambassador_phone'] ?? null,
                    'working_computers' => 0,
                    'created_by' => $request->user()->username,
                ]);

                $user = User::create([
                    'name' => trim($data['first_name'].' '.$data['last_name']),
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'username' => $data['branch_code'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'id_number' => $data['id_number'],
                    'password' => 'abc@123',
                    'role' => 'branch-manager',
                    'branch_id' => $branch->id,
                ]);

                return response()->json(['user' => $user], 201);
            });
        }

        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        $data['password'] = blank($data['password'] ?? null) ? 'abc@123' : $data['password'];
        $username = $this->usernameFor($data['first_name']);
        if (User::query()->where('username', $username)->exists()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['first_name' => ['This name is already taken. Please add one letter to the name for clarification.']],
            ], 422);
        }
        $data['username'] = $username;
        $user = User::create($data);

        return response()->json(['user' => $user], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureServiceDeskManager($request);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]+$/'],
            'id_number' => ['required', 'string', 'max:80', 'unique:users,id_number,' . $user->id],
            'role' => ['required', 'in:ambassador,service-desk-manager,branch-manager'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'branch_code' => ['required_if:role,branch-manager', 'nullable', 'string', 'max:30', 'regex:/^[0-9]+$/', Rule::unique('users', 'username')->ignore($user->id), Rule::unique('branches', 'branch_code')->ignore($user->branch_id)],
            'branch_name' => ['required_if:role,branch-manager', 'nullable', 'string', 'max:120'],
            'manager_name' => ['required_if:role,branch-manager', 'nullable', 'string', 'max:120'],
            'manager_phone' => ['required_if:role,branch-manager', 'nullable', 'string', 'max:30', 'regex:/^[0-9+()\\\\-\\\\s]+$/'],
            'assistant_name' => ['nullable', 'string', 'max:120'],
            'assistant_mobile' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\\\-\\\\s]+$/'],
            'ambassador' => ['nullable', Rule::exists('users', 'username')->where(fn ($query) => $query->where('role', 'ambassador'))],
            'backup_ambassador' => ['nullable', Rule::exists('users', 'username')->where(fn ($query) => $query->where('role', 'ambassador'))],
            'backup_ambassador_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\\\-\\\\s]+$/'],
        ]);

        $role = $request->input('role', $user->role);

        if ($role === 'branch-manager' || $user->role === 'branch-manager') {
            return DB::transaction(function () use ($request, $user, $data, $role) {
                $branch = $user->branch ?: Branch::create([
                    'branch_code' => $data['branch_code'] ?? $user->username,
                    'branch_type' => 'Branch',
                    'working_computers' => 0,
                    'created_by' => $request->user()->username,
                ]);

                if ($role === 'branch-manager') {
                    $branch->update([
                        'branch_code' => $data['branch_code'],
                        'branch_name' => $data['branch_name'],
                        'manager_name' => $data['manager_name'],
                        'manager_phone' => $data['manager_phone'],
                        'assistant_name' => $data['assistant_name'] ?? null,
                        'assistant_mobile' => $data['assistant_mobile'] ?? null,
                    ]);

                    $ambassadorUser = $data['ambassador'] ? User::query()->where('username', $data['ambassador'])->first() : null;
                    $backupAmbassadorUser = $data['backup_ambassador'] ? User::query()->where('username', $data['backup_ambassador'])->first() : null;

                    $branch->update([
                        'ambassador_name' => $ambassadorUser?->name,
                        'ambassador_phone' => $ambassadorUser?->phone,
                        'backup_username' => $data['backup_ambassador'] ?? null,
                        'backup_name' => $backupAmbassadorUser?->name,
                        'backup_phone' => $data['backup_ambassador_phone'] ?? null,
                    ]);

                    $user->update([
                        'name' => trim($data['first_name'].' '.$data['last_name']),
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'username' => $data['branch_code'],
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                        'id_number' => $data['id_number'],
                        'role' => 'branch-manager',
                        'branch_id' => $branch->id,
                    ]);
                }

                return response()->json(['user' => $user->fresh()], 200);
            });
        }

        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        $data['username'] = $user->role === 'ambassador' || $data['role'] === 'ambassador'
            ? $this->uniqueUsername($this->usernameFor($data['first_name']), $user->id)
            : $user->username;

        if (blank($data['password'] ?? null)) unset($data['password']);
        $user->update($data);

        return response()->json(['user' => $user->fresh()]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->ensureServiceDeskManager($request);
        abort_if($user->is($request->user()), 422, 'You cannot delete your own account.');
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    private function ensureServiceDeskManager(Request $request): void
    {
        abort_unless($request->user()?->role === 'service-desk-manager', 403, 'Only service desk managers can manage users.');
    }

    private function usernameFor(string $firstName): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $firstName));
    }

    private function uniqueUsername(string $username, ?int $ignoreId = null): string
    {
        if (User::query()->where('username', $username)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            throw ValidationException::withMessages([
                'first_name' => 'This name is already taken. Please add one letter to the name for clarification.',
            ]);
        }
        return $username;
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
        protected function assertCanAssignRoles(Request $request, array $roleIds): void
    {
        if (empty($roleIds) || $request->user()->type === 'admin') {
            return;
        }

        $hasSuper = \App\Models\Role::whereIn('id', $roleIds)->where('slug', 'super-admin')->exists();
        abort_if($hasSuper, 403, 'لا يمكنك منح دور Super Admin');
    }
    public function index(Request $request)
    {
        $employees = User::employees()
            ->with('roles')
           ->when($request->filled('search'), function ($q) use ($request) {
    $search = trim($request->string('search')->toString());

    $q->where(function ($searchQuery) use ($search) {
        $searchQuery->where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%");
            })
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate(20);

        return UserResource::collection($employees);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'phone'     => 'nullable|string|unique:users,phone',
            'password'  => 'required|string|min:8',
            'is_active' => 'boolean',
            'role_ids'  => 'nullable|array',
            'role_ids.*'=> 'exists:roles,id',
        ]);
$this->assertCanAssignRoles($request, $data['role_ids'] ?? []);
        $employee = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => Hash::make($data['password']),
            'type'      => 'employee',
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (!empty($data['role_ids'])) {
            $employee->roles()->sync($data['role_ids']);
        }

        return (new UserResource($employee->load('roles')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $employee)
    {
        abort_if($employee->type !== 'employee', 404);

        return new UserResource($employee->load('roles.permissions'));
    }

    public function update(Request $request, User $employee)
    {
        abort_if($employee->type !== 'employee', 404);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|unique:users,email,' . $employee->id,
            'phone'     => 'nullable|string|unique:users,phone,' . $employee->id,
            'password'  => 'nullable|string|min:8',
            'is_active' => 'boolean',
            'role_ids'  => 'nullable|array',
            'role_ids.*'=> 'exists:roles,id',
        ]);
$this->assertCanAssignRoles($request, $data['role_ids'] ?? []);
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $employee->update($data);

        if (isset($data['role_ids'])) {
            $employee->roles()->sync($data['role_ids']);
        }

        return new UserResource($employee->load('roles'));
    }

    public function destroy(User $employee)
    {
        abort_if($employee->type !== 'employee', 404);

        $employee->delete();

        return response()->json(['message' => 'تم حذف الموظف بنجاح']);
    }

    public function toggleStatus(User $employee)
    {
        abort_if($employee->type !== 'employee', 404);

        $employee->update([
            'is_active' => !$employee->is_active
        ]);

        return new UserResource($employee);
    }
}

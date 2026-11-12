<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\PermissionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
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
                });
            })
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', $request->boolean('is_active'))
            )
            ->latest()
            ->paginate(20);

        return UserResource::collection($employees);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'nullable|string|unique:users,phone',
            'password'   => 'required|string|min:8',
            'is_active'  => 'boolean',
            'role_ids'   => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        PermissionGuard::assertCanGrantRoleIds($request->user(), $data['role_ids'] ?? []);

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

        AuditLog::record('employee.created', $employee, ['role_ids' => $data['role_ids'] ?? []]);

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

        // مش نفسك، ومش موظف صلاحياته فوق صلاحياتك
        PermissionGuard::assertCanManageUser($request->user(), $employee);

        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|email|unique:users,email,' . $employee->id,
            'phone'      => 'nullable|string|unique:users,phone,' . $employee->id,
            'password'   => 'nullable|string|min:8',
            'is_active'  => 'boolean',
            'role_ids'   => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $roleIds = $data['role_ids'] ?? null;

        if (array_key_exists('role_ids', $data)) {
            PermissionGuard::assertCanGrantRoleIds($request->user(), $roleIds ?? []);
        }

        unset($data['role_ids']);

        $passwordChanged = !empty($data['password']);

        if ($passwordChanged) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $employee->update($data);

        if ($roleIds !== null) {
            $employee->roles()->sync($roleIds);
        }

        // تغيير الباسورد أو تعطيل الحساب = قفل كل الجلسات
        if ($passwordChanged || ($data['is_active'] ?? true) === false) {
            $employee->tokens()->delete();
        }

        AuditLog::record('employee.updated', $employee, [
            'fields'           => array_keys($data),
            'roles_changed'    => $roleIds !== null,
            'password_changed' => $passwordChanged,
        ]);

        return new UserResource($employee->load('roles'));
    }

    public function destroy(Request $request, User $employee)
    {
        abort_if($employee->type !== 'employee', 404);

        PermissionGuard::assertCanManageUser($request->user(), $employee);

        $employee->tokens()->delete();
        $employee->delete();

        AuditLog::record('employee.deleted', $employee);

        return response()->json(['message' => 'تم حذف الموظف بنجاح']);
    }

    public function toggleStatus(Request $request, User $employee)
    {
        abort_if($employee->type !== 'employee', 404);

        PermissionGuard::assertCanManageUser($request->user(), $employee);

        $employee->update(['is_active' => !$employee->is_active]);

        if (!$employee->is_active) {
            $employee->tokens()->delete();
        }

        AuditLog::record('employee.toggled', $employee, ['is_active' => $employee->is_active]);

        return new UserResource($employee);
    }
}

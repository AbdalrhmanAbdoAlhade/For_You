<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\AuditLog;
use App\Support\PermissionGuard;

class RoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::with('permissions')->withCount('users')->get());
    }

    public function show(Role $role)
    {
        return response()->json($role->load('permissions')->loadCount('users'));
    }

      public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255|unique:roles,name',
            'description'   => 'nullable|string',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // مينفعش تعمل دور فيه صلاحيات إنت نفسك مش معاك
        PermissionGuard::assertCanGrantPermissionIds($request->user(), $data['permissions'] ?? []);

        $role = Role::create([
            'name'        => $data['name'],
            'slug'        => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active'   => true,
        ]);

        if (!empty($data['permissions'])) {
            $role->permissions()->sync($data['permissions']);
        }

        AuditLog::record('role.created', $role, ['permissions' => $data['permissions'] ?? []]);

        return response()->json($role->load('permissions'), 201);
    }

    public function update(Request $request, Role $role)
    {
        if ($this->isSystemRole($role)) {
            return response()->json(['message' => 'لا يمكن تعديل دور Super Admin'], 403);
        }

        PermissionGuard::assertCanManageRole($request->user(), $role);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
            'description'   => 'nullable|string',
            'is_active'     => 'boolean',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if (array_key_exists('permissions', $data)) {
            PermissionGuard::assertCanGrantPermissionIds($request->user(), $data['permissions'] ?? []);
        }

        // الـ slug معرّف ثابت، مبنغيرهوش لما الاسم يتغير
        $role->update(collect($data)->except('permissions')->all());

        if (array_key_exists('permissions', $data)) {
            $role->permissions()->sync($data['permissions'] ?? []);
        }

        AuditLog::record('role.updated', $role, [
            'fields'      => array_keys(collect($data)->except('permissions')->all()),
            'permissions' => $data['permissions'] ?? null,
        ]);

        return response()->json($role->load('permissions'));
    }

    public function destroy(Request $request, Role $role)
    {
        if ($this->isSystemRole($role)) {
            return response()->json(['message' => 'لا يمكن حذف دور Super Admin'], 403);
        }

        PermissionGuard::assertCanManageRole($request->user(), $role);

        if ($role->users()->exists()) {
            return response()->json(
                ['message' => 'لا يمكن حذف دور مرتبط بموظفين، انقلهم لدور آخر أولاً'],
                422
            );
        }

        $role->permissions()->detach();
        $role->delete();

        AuditLog::record('role.deleted', $role);

        return response()->json(['message' => 'تم حذف الدور بنجاح']);
    }

    public function permissions()
    {
        return response()->json(Permission::all()->groupBy('group'));
    }

    protected function isSystemRole(Role $role): bool
    {
        return $role->slug === 'super-admin';
    }

    protected function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name) ?: 'role';
        $base = $slug;
        $i = 2;

        // يمنع إنشاء دور بـ slug محجوز زي super-admin
        while (Role::where('slug', $slug)->exists() || $slug === 'super-admin') {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}

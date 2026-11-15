<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class PermissionGuard
{
    /** كل slugs الصلاحيات اللي المستخدم يملكها (الأدمن = الكل) */
    public static function slugsOf(User $user): Collection
    {
        if ($user->type === 'admin') {
            return Permission::pluck('slug');
        }

        return $user->permissionSlugs();
    }

    /** منح صلاحيات (بالـ ids): لازم الفاعل يملكها كلها */
    public static function assertCanGrantPermissionIds(User $actor, array $permissionIds): void
    {
        if ($actor->type === 'admin' || empty($permissionIds)) {
            return;
        }

        $missing = Permission::whereIn('id', $permissionIds)->pluck('slug')
            ->diff(static::slugsOf($actor));

        abort_if(
            $missing->isNotEmpty(),
            403,
            'لا يمكنك منح صلاحيات لا تملكها: ' . $missing->implode(', ')
        );
    }

    /** إسناد أدوار لموظف: ممنوع Super Admin، وكل صلاحيات الأدوار لازم تكون ضمن صلاحيات الفاعل */
    public static function assertCanGrantRoleIds(User $actor, array $roleIds): void
    {
        if ($actor->type === 'admin' || empty($roleIds)) {
            return;
        }

        abort_if(
            Role::whereIn('id', $roleIds)->where('slug', 'super-admin')->exists(),
            403,
            'لا يمكنك منح دور Super Admin'
        );

        $missing = Permission::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
            ->pluck('slug')
            ->diff(static::slugsOf($actor));

        abort_if(
            $missing->isNotEmpty(),
            403,
            'الأدوار دي فيها صلاحيات لا تملكها: ' . $missing->implode(', ')
        );
    }

    /** إدارة موظف (تعديل/تعطيل/حذف/باسورد): مش نفسك، ومش موظف صلاحياته أعلى منك */
    public static function assertCanManageUser(User $actor, User $target): void
    {
        if ($actor->type === 'admin') {
            return;
        }

        abort_if($actor->id === $target->id, 403, 'لا يمكنك تعديل حسابك من هنا');

        $missing = static::slugsOf($target)->diff(static::slugsOf($actor));

        abort_if(
            $missing->isNotEmpty(),
            403,
            'لا يمكنك إدارة موظف صلاحياته أعلى من صلاحياتك'
        );
    }

    /** تعديل/حذف دور: مش دور مُسنَد ليك، ومش دور فيه صلاحيات فوق صلاحياتك */
    public static function assertCanManageRole(User $actor, Role $role): void
    {
        if ($actor->type === 'admin') {
            return;
        }

        abort_if(
            $actor->roles()->whereKey($role->id)->exists(),
            403,
            'لا يمكنك تعديل دور مُسنَد لك'
        );

        $missing = $role->permissions()->pluck('slug')->diff(static::slugsOf($actor));

        abort_if(
            $missing->isNotEmpty(),
            403,
            'لا يمكنك تعديل دور فيه صلاحيات لا تملكها'
        );
    }
}

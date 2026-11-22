<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BrandFollowService
{
    /**
     * @return array{following:bool, followers_count:int}
     */
    public function follow(User $user, Brand $brand): array
    {
        // insertOrIgnore: الطلب المتكرر مابيعملش خطأ (unique على user + brand)
        DB::table('brand_follows')->insertOrIgnore([
            'user_id'    => $user->id,
            'brand_id'   => $brand->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->state($brand, true);
    }

    /**
     * @return array{following:bool, followers_count:int}
     */
    public function unfollow(User $user, Brand $brand): array
    {
        DB::table('brand_follows')
            ->where('user_id', $user->id)
            ->where('brand_id', $brand->id)
            ->delete();

        return $this->state($brand, false);
    }

    public function isFollowing(User $user, int $brandId): bool
    {
        return DB::table('brand_follows')
            ->where('user_id', $user->id)
            ->where('brand_id', $brandId)
            ->exists();
    }

    /**
     * أيهم من البراندات دي المستخدم بيتابعها (لعلامة is_following في القوائم من غير N+1).
     *
     * @param  array<int> $brandIds
     * @return array<int>
     */
    public function followedAmong(User $user, array $brandIds): array
    {
        if ($brandIds === []) {
            return [];
        }

        return DB::table('brand_follows')
            ->where('user_id', $user->id)
            ->whereIn('brand_id', $brandIds)
            ->pluck('brand_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function state(Brand $brand, bool $following): array
    {
        return [
            'following'       => $following,
            'followers_count' => (int) DB::table('brand_follows')
                ->where('brand_id', $brand->id)
                ->count(),
        ];
    }
}
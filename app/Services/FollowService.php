<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FollowService
{
    /**
     * @return array{following:bool, followers_count:int}
     */
    public function follow(User $user, User $influencer): array
    {
        if ($influencer->type !== 'influencer' || !$influencer->is_active) {
            throw ValidationException::withMessages([
                'influencer' => ['المؤثر ده مش متاح'],
            ]);
        }

        if ($user->id === $influencer->id) {
            throw ValidationException::withMessages([
                'influencer' => ['مينفعش تتابع نفسك'],
            ]);
        }

        // insertOrIgnore: الطلب المتكرر مابيعملش خطأ (unique على user + influencer)
        DB::table('influencer_follows')->insertOrIgnore([
            'user_id'       => $user->id,
            'influencer_id' => $influencer->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return $this->state($influencer, true);
    }

    /**
     * @return array{following:bool, followers_count:int}
     */
    public function unfollow(User $user, User $influencer): array
    {
        DB::table('influencer_follows')
            ->where('user_id', $user->id)
            ->where('influencer_id', $influencer->id)
            ->delete();

        return $this->state($influencer, false);
    }

    public function isFollowing(User $user, int $influencerId): bool
    {
        return DB::table('influencer_follows')
            ->where('user_id', $user->id)
            ->where('influencer_id', $influencerId)
            ->exists();
    }

    /**
     * أيهم من المؤثرين دول المستخدم بيتابعه (لعلامة is_following في القوائم من غير N+1).
     *
     * @param  array<int> $influencerIds
     * @return array<int>
     */
    public function followedAmong(User $user, array $influencerIds): array
    {
        if ($influencerIds === []) {
            return [];
        }

        return DB::table('influencer_follows')
            ->where('user_id', $user->id)
            ->whereIn('influencer_id', $influencerIds)
            ->pluck('influencer_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * عدد المتابعين لكل مؤثر [influencer_id => count].
     *
     * @param  array<int> $influencerIds
     * @return array<int,int>
     */
    public function followersCounts(array $influencerIds): array
    {
        if ($influencerIds === []) {
            return [];
        }

        return DB::table('influencer_follows')
            ->whereIn('influencer_id', $influencerIds)
            ->selectRaw('influencer_id, COUNT(*) as total')
            ->groupBy('influencer_id')
            ->pluck('total', 'influencer_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    protected function state(User $influencer, bool $following): array
    {
        return [
            'following'       => $following,
            'followers_count' => (int) DB::table('influencer_follows')
                ->where('influencer_id', $influencer->id)
                ->count(),
        ];
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function __construct(
        protected FollowService $follows
    ) {}

    /** POST influencers/{id}/follow */
    public function follow(Request $request, int $id)
    {
        $influencer = User::influencers()->active()->findOrFail($id);

        return response()->json($this->follows->follow($request->user(), $influencer));
    }

    /** DELETE influencers/{id}/follow */
    public function unfollow(Request $request, int $id)
    {
        $influencer = User::influencers()->findOrFail($id);

        return response()->json($this->follows->unfollow($request->user(), $influencer));
    }

    /** GET following: كل المؤثرين اللي بتابعهم ("See all") */
    public function following(Request $request)
    {
        $list = $request->user()->following()
            ->where('users.is_active', true)
            ->where('users.type', 'influencer')
            ->orderBy('users.name')
            ->paginate(20);

        $list->through(fn ($u) => [
            'id'     => $u->id,
            'name'   => $u->name,
            'avatar' => $u->avatar,
        ]);

        return response()->json($list);
    }
}
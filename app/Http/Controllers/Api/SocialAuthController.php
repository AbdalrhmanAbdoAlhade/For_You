<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect to provider (للويب)
     */
    public function redirect(string $provider)
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Callback من Google / Apple (للويب)
     */
    public function callback(string $provider)
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return response()->json(['message' => 'فشل تسجيل الدخول الاجتماعي'], 401);
        }

        $user = $this->findOrCreateUser($socialUser, $provider);

        $token = $user->createToken('mobile')->plainTextToken;

        // لو ويب → تقدر تعمل redirect ومعاك التوكن
        // لو API → نرجع JSON
        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    /**
     * Login من الموبايل (بيستقبل الـ access_token أو id_token)
     */
    public function loginWithToken(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        $request->validate([
            'access_token' => 'required_without:id_token|string',
            'id_token'     => 'required_without:access_token|string', // Apple عادة بيبعت id_token
        ]);

        try {
            if ($provider === 'apple') {
                $socialUser = Socialite::driver('apple')
                    ->stateless()
                    ->userFromToken($request->id_token ?? $request->access_token);
            } else {
                $socialUser = Socialite::driver($provider)
                    ->stateless()
                    ->userFromToken($request->access_token);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'توكن غير صالح أو منتهي',
                'error'   => $e->getMessage()
            ], 401);
        }

        $user = $this->findOrCreateUser($socialUser, $provider);

        if (!$user->is_active) {
            return response()->json(['message' => 'الحساب موقوف'], 403);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    /**
     * البحث عن المستخدم أو إنشاؤه
     */
    protected function findOrCreateUser($socialUser, string $provider): User
    {
        // 1. دور بالـ provider_id
        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($user) {
            return $user;
        }

        // 2. دور بالإيميل (لو موجود نربطه)
        if ($socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'provider'    => $provider,
                    'provider_id' => $socialUser->getId(),
                    'avatar'      => $socialUser->getAvatar() ?? $user->avatar,
                ]);
                return $user;
            }
        }

        // 3. إنشاء مستخدم جديد
        return User::create([
            'name'        => $socialUser->getName() ?? 'User ' . Str::random(5),
            'email'       => $socialUser->getEmail(), // ممكن يكون null في Apple
            'password'    => null,
            'provider'    => $provider,
            'provider_id' => $socialUser->getId(),
            'avatar'      => $socialUser->getAvatar(),
            'type'        => 'customer',
            'is_active'   => true,
        ]);
    }

    protected function validateProvider(string $provider): void
    {
        if (!in_array($provider, ['google', 'apple'])) {
            abort(422, 'Provider غير مدعوم');
        }
    }
}

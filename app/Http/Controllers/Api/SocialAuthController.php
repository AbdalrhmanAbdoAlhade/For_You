<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect to provider (للويب)
     */
    public function redirect(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        /*
         * الـ API stateless (مفيش session)، فبنعمل state عشوائي في الـ cache
         * ونتحقق منه في الـ callback. ده يمنع login CSRF: حد يخلي الضحية
         * يكمل تسجيل دخول بحساب المهاجم.
         */
        $state = Str::random(40);
        Cache::put('social:state:' . $state, $provider, now()->addMinutes(10));

        return Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $state])
            ->redirect();
    }

    /**
     * Callback من Google / Apple (للويب): الكود بيتبدل بتوكن من عندنا، فآمن
     */
    public function callback(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        $stateInput = $request->input('state');
        $state = is_string($stateInput) ? $stateInput : '';

        if ($state === '' || Cache::pull('social:state:' . $state) !== $provider) {
            return response()->json([
                'message' => 'حالة تسجيل الدخول غير صالحة أو منتهية',
            ], 401);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'فشل تسجيل الدخول الاجتماعي'], 401);
        }

        return $this->respond($this->fromSocialite($socialUser), $provider);
    }

    /**
     * Login من الموبايل: Google بـ id_token (بيتتحقق منه)، Apple بـ id_token
     */
    public function loginWithToken(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        if ($provider === 'google') {
            $request->validate(['id_token' => 'required|string']);

            $profile = $this->verifyGoogleIdToken($request->input('id_token'));
        } else {
            $request->validate([
                'id_token'     => 'required_without:access_token|string',
                'access_token' => 'required_without:id_token|string',
            ]);

            $idToken = $request->input('id_token');

            // التحقق من aud/iss/exp محليًا قبل الاعتماد على المكتبة.
            if ($idToken && !$this->verifyAppleIdTokenClaims((string) $idToken)) {
                return response()->json(['message' => 'توكن غير صالح أو منتهي'], 401);
            }

            try {
                $socialUser = Socialite::driver('apple')
                    ->stateless()
                    ->userFromToken($idToken ?? $request->input('access_token'));

                $profile = $this->fromSocialite($socialUser);
            } catch (\Throwable $e) {
                report($e);
                $profile = null;
            }
        }

        if (!$profile) {
            return response()->json(['message' => 'توكن غير صالح أو منتهي'], 401);
        }

        return $this->respond($profile, $provider);
    }

    // ==================== helpers ====================

    protected function respond(array $profile, string $provider): JsonResponse
    {
        try {
            $user = $this->findOrCreateUser($profile, $provider);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $user->createToken('social')->plainTextToken,
        ]);
    }

    /** يوحّد بيانات Socialite في شكل واحد */
    protected function fromSocialite($socialUser): array
    {
        $raw = (array) ($socialUser->user ?? []);

        return [
            'id'             => (string) $socialUser->getId(),
            'email'          => $socialUser->getEmail() ? mb_strtolower(trim($socialUser->getEmail())) : null,
            'email_verified' => filter_var(
                $raw['email_verified'] ?? $raw['verified_email'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ),
            'name'           => $socialUser->getName(),
            'avatar'         => $socialUser->getAvatar(),
        ];
    }

    /**
     * التحقق من Google id_token: التوقيع والانتهاء عند Google، والـ aud والـ iss عندنا.
     * بيرجّع null لو أي حاجة غلط.
     */
    protected function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        $claims  = $response->json();
        $allowed = config('services.google.client_ids', []);

        if (empty($allowed) || !in_array($claims['aud'] ?? null, $allowed, true)) {
            return null;
        }

        if (!in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) {
            return null;
        }

        if (empty($claims['sub'])) {
            return null;
        }

        return [
            'id'             => (string) $claims['sub'],
            'email'          => !empty($claims['email']) ? mb_strtolower(trim($claims['email'])) : null,
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'name'           => $claims['name'] ?? null,
            'avatar'         => $claims['picture'] ?? null,
        ];
    }

    /**
     * التحقق المحلي من claims الـ Apple id_token.
     *
     * التوقيع نفسه بيتحقق منه socialiteproviders/apple (مفاتيح Apple)،
     * وهنا نتأكد من aud و iss و exp قبل قبول التوكن.
     */
    protected function verifyAppleIdTokenClaims(string $idToken): bool
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            return false;
        }

        $encoded = strtr($parts[1], '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $payload = json_decode((string) base64_decode($encoded, true), true);

        if (!is_array($payload)) {
            return false;
        }

        $clientId = (string) config('services.apple.client_id', '');

        if ($clientId !== '' && (string) ($payload['aud'] ?? '') !== $clientId) {
            return false;
        }

        if (($payload['iss'] ?? null) !== 'https://appleid.apple.com') {
            return false;
        }

        if (!isset($payload['exp']) || (int) $payload['exp'] <= now()->timestamp) {
            return false;
        }

        return true;
    }

    /**
     * البحث عن المستخدم أو إنشاؤه.
     *
     * @throws \DomainException (الكود = HTTP status)
     */
    protected function findOrCreateUser(array $profile, string $provider): User
    {
        // 1. نفس الحساب الاجتماعي
        $user = User::withTrashed()
            ->where('provider', $provider)
            ->where('provider_id', $profile['id'])
            ->first();

        if ($user) {
            return $this->assertAllowed($user);
        }

        // 2. نفس الإيميل: ربط بحذر
        if ($profile['email']) {
            $existing = User::withTrashed()->where('email', $profile['email'])->first();

            if ($existing) {
                if (!$profile['email_verified']) {
                    throw new \DomainException('الإيميل غير مؤكد من مزود الدخول', 409);
                }

                $this->assertAllowed($existing);

                if ($existing->provider
                    && ($existing->provider !== $provider || (string) $existing->provider_id !== $profile['id'])) {
                    throw new \DomainException('الحساب مربوط بطريقة تسجيل دخول تانية', 409);
                }

                // حساب بباسورد وإيميله مش مؤكد: ممكن يكون متسجل مسبقًا بواسطة شخص تاني
                if ($existing->password !== null && !$existing->email_verified_at) {
                    throw new \DomainException('الإيميل مسجل بباسورد، سجّل دخول بالباسورد', 409);
                }

                $existing->forceFill([
                    'provider'          => $provider,
                    'provider_id'       => $profile['id'],
                    'avatar'            => $profile['avatar'] ?? $existing->avatar,
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                ])->save();

                return $existing;
            }
        }

        // 3. إنشاء مستخدم جديد
        try {
            $user = new User([
                'name'        => $profile['name'] ?: 'User ' . Str::random(5),
                'email'       => $profile['email'],        // ممكن يكون null في Apple
                'password'    => null,
                'provider'    => $provider,
                'provider_id' => $profile['id'],
                'avatar'      => $profile['avatar'],
                'type'        => 'customer',
                'is_active'   => true,
            ]);

            if ($profile['email'] && $profile['email_verified']) {
                $user->email_verified_at = now();
            }

            $user->save();
        } catch (UniqueConstraintViolationException $e) {
            // طلبين متزامنين لنفس الحساب، أو إيميل محجوز
            throw new \DomainException('تعذر إنشاء الحساب، حاول مرة أخرى', 409);
        }

        return $user;
    }

    /** الدخول الاجتماعي للعملاء النشطين بس (مش أدمن/موظف/مؤثر) */
    protected function assertAllowed(User $user): User
    {
        if ($user->trashed()) {
            throw new \DomainException('الحساب محذوف', 403);
        }

        if ($user->type !== 'customer') {
            throw new \DomainException('تسجيل الدخول الاجتماعي غير متاح لهذا الحساب', 403);
        }

        if (!$user->is_active) {
            throw new \DomainException('الحساب موقوف', 403);
        }

        return $user;
    }

    protected function validateProvider(string $provider): void
    {
        if (!in_array($provider, ['google', 'apple'], true)) {
            abort(422, 'Provider غير مدعوم');
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUploadService
    ) {}

    public function register(Request $request)
{
    $data = $request->validate([
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|unique:users,email',
        'phone'    => 'nullable|string|unique:users,phone',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = User::create([
        'name'     => $data['name'],
        'email'    => $data['email'],
        'phone'    => $data['phone'] ?? null,
        'password' => $data['password'], // الـ cast 'hashed' في الـ Model هيعمل الـ Hash
        'type'     => 'customer',
    ]);

    $token = $user->createToken('mobile')->plainTextToken;

    return response()->json([
        'user'  => $user,
        'token' => $token,
    ], 201);
}


    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|string|email|max:255',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        // مستخدم Google/Apple من غير باسورد مينفعش يدخل بالباسورد
        if (!$user || !$user->password || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'الحساب موقوف'], 403);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج']);
    }

    // ==================== حساب العميل ====================

    /**
     * تعديل البيانات. لو فيه avatar ابعت POST مع _method=PUT (multipart).
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'email'            => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'            => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)],
            'avatar'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=2000,max_height=2000',
            'current_password' => 'nullable|string',
        ]);

        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        // الإيميل لحسابات العملاء بس (المؤثر والموظف بيتعدل من الإدارة)
        if ($emailChanged) {
            if ($user->type !== 'customer') {
                return response()->json(['message' => 'لتغيير البريد الإلكتروني تواصل مع الإدارة'], 403);
            }

            /*
             * حساب social من غير باسورد: تغيير الإيميل بتوكن واحد خطر
             * (توكن مسروق يغيّر الإيميل ويستولي على الحساب). نطلب تعيين
             * كلمة مرور أولاً، وبعدها التغيير يحتاج الباسورد الحالي.
             */
            if (!$user->password) {
                return response()->json([
                    'message' => 'عيّن كلمة مرور للحساب أولاً قبل تغيير البريد الإلكتروني',
                ], 403);
            }

            if (empty($data['current_password']) || !Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['كلمة المرور الحالية مطلوبة وصحيحة لتغيير البريد'],
                ]);
            }
        }

        unset($data['current_password']);

        if ($request->hasFile('avatar')) {
            // صورة الـ social (رابط خارجي) متتمسحش
            if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
                $this->fileUploadService->delete($user->avatar);
            }
            $data['avatar'] = $this->fileUploadService->upload($request->file('avatar'), 'avatars');
        } else {
            unset($data['avatar']);
        }

        $user->fill($data);

        // الإيميل الجديد لازم يتأكد من جديد.
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json($user->fresh());
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => 'nullable|string',
            'password'         => 'required|string|min:8|confirmed|different:current_password',
        ]);

        // لو الحساب ليه باسورد لازم الحالي يكون صح (مستخدم social بيعين باسورد لأول مرة من غيره)
        if ($user->password
            && (empty($data['current_password']) || !Hash::check($data['current_password'], $user->password))) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة'],
            ]);
        }

        $user->update(['password' => $data['password']]); // الـ cast 'hashed' بيعمل الـ Hash

        // نقفل كل الجلسات التانية ونسيب الحالية
        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
            ->delete();

        return response()->json(['message' => 'تم تغيير كلمة المرور']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->is_active) {
            try {
                Password::sendResetLink(['email' => $data['email']]);
            } catch (\Throwable $e) {
                report($e); // مشكلة إعدادات الميل متظهرش للعميل
            }
        }

        // نفس الرد دايماً عشان محدش يعرف مين مسجل
        return response()->json([
            'message' => 'لو البريد مسجل لدينا سيصلك رابط إعادة تعيين كلمة المرور',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete(); // نقفل كل الجلسات
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $message = $status === Password::RESET_THROTTLED
                ? 'حاول مرة أخرى بعد قليل'
                : 'رابط إعادة التعيين غير صالح أو منتهي';

            throw ValidationException::withMessages(['email' => [$message]]);
        }

        return response()->json(['message' => 'تم تغيير كلمة المرور، سجّل الدخول بالباسورد الجديد']);
    }
}

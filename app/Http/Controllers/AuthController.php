<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed', 'phone' => 'required|string|max:20',
            'address' => 'required|string',
        ]);
        $validator->validate();

        // Only use validated fields. Accepting arbitrary request data here
        // previously allowed public registration to choose privileged roles.
        $data = collect($validator->validated())->except(['password', 'password_confirmation'])->all();
        $data['password'] = Hash::make($request->password);
        $data['acc_status'] = 'active';
        $user = DB::transaction(function () use ($data): User {
            $user = User::create($data);
            $user->syncRoles(['viewer']);

            abort_unless($user->roles()->exists(), 500, 'business.default_role_missing');

            return $user;
        });
        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Account created. Check your email to verify your address before signing in.',
            'data' => $this->accountData($user),
            'email_verification_required' => true,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'sometimes|boolean',
            'two_factor_code' => 'nullable|digits:6',
        ]);
        $validator->validate();

        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'code' => 'auth.invalid_credentials'], 401);
        }
        if ($user->acc_status !== 'active') {
            return response()->json(['success' => false, 'code' => 'auth.account_inactive'], 403);
        }
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'email_verification_required' => true,
            ], 403);
        }
        if ($user->must_set_password) {
            return response()->json(['success' => false, 'code' => 'auth.password_setup_required'], 403);
        }
        if ($user->two_factor_confirmed_at && ! $this->validTotp($user->two_factor_secret, (string) $request->two_factor_code)) {
            return response()->json(['success' => false, 'two_factor_required' => true], 422);
        }

        $tokens = $this->issueTokens($user, $request->boolean('remember'));

        return response()->json(['success' => true, 'data' => array_merge(['user' => $this->accountData($user)], $tokens)]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));

        // Always return the same response so this endpoint cannot enumerate accounts.
        return response()->json(['success' => true, 'message' => 'If an account exists for that email, a password reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $credentials = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset($credentials, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['success' => false, 'code' => 'business.reset_token_invalid'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Password reset successfully. You can now sign in.']);
    }

    public function completeInvitation(Request $request)
    {
        $credentials = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $invitedUser = User::where('email', $credentials['email'])->first();
        if (! $invitedUser || ! $invitedUser->hasVerifiedEmail() || ! $invitedUser->must_set_password) {
            return response()->json(['success' => false, 'code' => 'business.invitation_invalid'], 422);
        }

        $status = Password::reset($credentials, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'must_set_password' => false,
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['success' => false, 'code' => 'business.invitation_invalid'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Password created. You can now sign in.']);
    }

    public function resendEmailVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', (string) $request->string('email'))->first();

        if ($user && $user->acc_status === 'active' && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['success' => true, 'message' => 'If verification is required, a new email has been sent.']);
    }

    public function verifyEmail(Request $request, string $id, string $hash)
    {
        $redirectUrl = url('/verify-email');
        if (! $request->hasValidSignature()) {
            return redirect()->to($redirectUrl.'?verified=0');
        }

        $user = User::find($id);
        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->to($redirectUrl.'?verified=0');
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($user->must_set_password) {
            $token = Password::broker()->createToken($user);

            return redirect()->to(url('/create-password').'?token='.urlencode($token).'&email='.urlencode($user->email));
        }

        return redirect()->to($redirectUrl.'?verified=1');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        if ($request->filled('refresh_token')) {
            $refreshToken = PersonalAccessToken::findToken((string) $request->string('refresh_token'));
            if ($refreshToken?->tokenable?->is($request->user())) {
                $refreshToken->delete();
            }
        }

        return response()->json(['success' => true, 'message' => 'Successfully logged out']);
    }

    public function me(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->accountData($request->user())]);
    }

    public function refreshToken(Request $request)
    {
        $data = $request->validate(['refresh_token' => 'required|string', 'remember' => 'sometimes|boolean']);
        $candidate = PersonalAccessToken::findToken($data['refresh_token']);
        if (! $candidate) {
            return response()->json(['success' => false, 'code' => 'auth.refresh_token_invalid'], 401);
        }

        $tokens = DB::transaction(function () use ($candidate, $request): ?array {
            $refreshToken = PersonalAccessToken::query()->lockForUpdate()->find($candidate->id);
            $user = $refreshToken?->tokenable;

            if (! $refreshToken
                || ! $refreshToken->can('refresh')
                || ($refreshToken->expires_at && $refreshToken->expires_at->isPast())
                || ! $user instanceof User
                || $user->acc_status !== 'active'
                || ! $user->hasVerifiedEmail()) {
                return null;
            }

            $remember = $request->has('remember')
                ? $request->boolean('remember')
                : $refreshToken->name === 'refresh-token-remembered';
            $refreshToken->delete();

            return $this->issueTokens($user, $remember);
        });

        if (! $tokens) {
            return response()->json(['success' => false, 'code' => 'auth.refresh_token_invalid'], 401);
        }

        return response()->json(['success' => true, 'data' => $tokens]);
    }

    /** Account tab: contact and sign-in details only. */
    public function updateAccount(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255', 'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'sometimes|nullable|string|max:20', 'address' => 'sometimes|nullable|string|max:2000',
        ]);
        $originalEmail = $user->email;
        $user->fill(collect($data)->only(['name', 'email', 'phone', 'address'])->all());
        if ($user->email !== $originalEmail) {
            $user->email_verified_at = null;
        }
        // These old preferences were never used. Locale belongs to the browser
        // and timezone/currency belong to company settings, not a user profile.
        $settings = $user->account_settings ?? [];
        foreach (['organization', 'state', 'zip', 'country', 'language', 'timezone', 'currency'] as $legacyKey) {
            unset($settings[$legacyKey]);
        }
        $user->account_settings = $settings;
        $user->save();
        if ($user->email !== $originalEmail) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['success' => true, 'data' => $this->accountData($user->fresh())]);
    }

    /** Stores only a generated filename. The employee column receives the public Storage URL. */
    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|file|image|mimes:jpg,jpeg,png,gif,webp|max:800']);
        $employee = $this->employeeFor($request->user());
        if (! $employee) {
            return response()->json(['success' => false, 'code' => 'business.employee_profile_missing'], 404);
        }

        $file = $request->file('avatar');
        $extension = $file->extension(); // derived from detected MIME type, never the client filename
        $path = 'avatars/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->putFileAs('avatars', $file, basename($path));
        $this->deleteAvatar($employee->avatar);
        $employee->update(['avatar' => '/storage/'.$path]);

        return response()->json(['success' => true, 'avatar' => $employee->avatar]);
    }

    public function removeAvatar(Request $request)
    {
        $employee = $this->employeeFor($request->user());
        if (! $employee) {
            return response()->json(['success' => false, 'code' => 'business.employee_profile_missing'], 404);
        }
        $this->deleteAvatar($employee->avatar);
        $employee->update(['avatar' => null]);

        return response()->json(['success' => true]);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'password' => 'required|string|min:8|confirmed']);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['success' => false, 'code' => 'validation_failed', 'errors' => ['current_password' => ['business.current_password_invalid']]], 422);
        }
        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        $user->tokens()->whereKeyNot($user->currentAccessToken()?->id)->delete();

        return response()->json(['success' => true, 'message' => 'Password updated. Other sessions have been signed out.']);
    }

    public function updateNotifications(Request $request)
    {
        $data = $request->validate([
            'notifications' => ['required', 'array'],
            'notifications.*.in_app' => ['required', 'boolean'],
        ]);
        $user = $request->user();
        $settings = $user->account_settings ?? [];
        unset($settings['notification_frequency']);
        $user->account_settings = array_merge($settings, ['notifications' => $data['notifications']]);
        $user->save();

        return response()->json(['success' => true, 'data' => $user->account_settings]);
    }

    public function beginTwoFactor(Request $request)
    {
        $user = $request->user();
        $secret = $this->base32Encode(random_bytes(20));
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();
        $issuer = rawurlencode(config('app.name'));
        $label = rawurlencode(config('app.name').':'.$user->email);

        return response()->json(['success' => true, 'secret' => $secret, 'otpauth_url' => "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30"]);
    }

    public function confirmTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);
        $user = $request->user();
        if (! $user->two_factor_secret || ! $this->validTotp($user->two_factor_secret, $request->code)) {
            return response()->json(['success' => false, 'code' => 'validation_failed', 'errors' => ['code' => ['business.two_factor_code_invalid']]], 422);
        }
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return response()->json(['success' => true]);
    }

    public function disableTwoFactor(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (! Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'code' => 'validation_failed', 'errors' => ['password' => ['business.current_password_invalid']]], 422);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        return response()->json(['success' => true]);
    }

    public function logoutOtherDevices(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (! Hash::check($request->password, $user->password)) {
            // The security page contains multiple password forms at once. A
            // dedicated key lets the client mark this form's field only.
            return response()->json(['success' => false, 'code' => 'validation_failed', 'errors' => ['logout_password' => ['business.current_password_invalid']]], 422);
        }
        $user->tokens()->whereKeyNot($user->currentAccessToken()?->id)->delete();

        return response()->json(['success' => true]);
    }

    private function accountData(User $user): array
    {
        $employee = $this->employeeFor($user);
        $isMarketer = $employee?->position?->system_key === 'marketer';

        return array_merge($user->only(['id', 'name', 'email', 'phone', 'address', 'acc_status']), $user->account_settings ?? [], [
            'roles' => $user->roleNames(),
            'permissions' => $user->permissionNames(),
            'avatar' => $employee?->avatar,
            'employee' => $employee ? [
                'id' => $employee->id,
                'position_id' => $employee->position_id,
                'is_marketer' => $isMarketer,
            ] : null,
            'is_marketer' => $isMarketer,
            'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
            'email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $user->email_verified_at?->toISOString(),
        ]);
    }

    private function issueTokens(User $user, bool $remember): array
    {
        $accessToken = $user->createToken('access-token', ['access'], now()->addHours(2));
        $refreshToken = $user->createToken(
            $remember ? 'refresh-token-remembered' : 'refresh-token-session',
            ['refresh'],
            $remember ? now()->addDays(30) : now()->addDay(),
        );

        return [
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 7200,
        ];
    }

    private function employeeFor(User $user): ?Employee
    {
        return Employee::with('position:id,system_key')->where('user_id', $user->id)->first();
    }

    private function deleteAvatar(?string $url): void
    {
        $path = parse_url((string) $url, PHP_URL_PATH) ?: $url;
        if (! is_string($path) || ! str_starts_with($path, '/storage/avatars/')) {
            return;
        }
        $storagePath = Str::after($path, '/storage/');
        if (str_contains($storagePath, '..') || str_contains($storagePath, '\\')) {
            return;
        }
        Storage::disk('public')->delete($storagePath);
    }

    private function validTotp(string $secret, string $code): bool
    {
        foreach ([-1, 0, 1] as $offset) {
            if (hash_equals($this->totp($secret, intdiv(time(), 30) + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function totp(string $secret, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 15;
        $value = ((ord($hash[$offset]) & 127) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        } $out = '';
        foreach (str_split($bits, 5) as $part) {
            $out .= $alphabet[bindec(str_pad($part, 5, '0'))];
        }

        return $out;
    }

    private function base32Decode(string $data): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $bits = '';
        foreach (str_split(str_replace('=', '', strtoupper($data))) as $char) {
            $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        } $out = '';
        foreach (str_split($bits, 8) as $part) {
            if (strlen($part) === 8) {
                $out .= chr(bindec($part));
            }
        }

        return $out;
    }
}

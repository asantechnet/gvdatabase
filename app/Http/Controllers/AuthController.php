<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed', 'phone' => 'required|string|max:20',
            'roles' => 'required|array', 'address' => 'required|string',
        ]);
        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        $data = $request->except('password', 'password_confirmation');
        $data['password'] = Hash::make($request->password);
        $data['roles'] = json_encode($request->roles);
        $data['acc_status'] = 'active';
        $user = User::create($data);
        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json(['success' => true, 'data' => $this->accountData($user), 'access_token' => $token, 'token_type' => 'Bearer'], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email', 'password' => 'required|string', 'two_factor_code' => 'nullable|digits:6']);
        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) return response()->json(['success' => false, 'message' => 'Invalid email or password'], 401);
        if ($user->acc_status !== 'active') return response()->json(['success' => false, 'message' => 'Account is not active'], 403);
        if ($user->two_factor_confirmed_at && !$this->validTotp($user->two_factor_secret, (string) $request->two_factor_code)) {
            return response()->json(['success' => false, 'two_factor_required' => true, 'message' => 'A valid two-factor authentication code is required.'], 422);
        }

        $token = $user->createToken('authToken')->plainTextToken;
        return response()->json(['success' => true, 'data' => ['user' => $this->accountData($user), 'access_token' => $token, 'refresh_token' => $token, 'token_type' => 'Bearer']]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['success' => true, 'message' => 'Successfully logged out']);
    }

    public function me(Request $request) { return response()->json(['success' => true, 'data' => $this->accountData($request->user())]); }

    public function refreshToken(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        $token = $request->user()->createToken('authToken')->plainTextToken;
        return response()->json(['success' => true, 'access_token' => $token, 'token_type' => 'Bearer']);
    }

    /** Vuexy Account tab: profile fields and account preferences. */
    public function updateAccount(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255', 'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'sometimes|nullable|string|max:20', 'address' => 'sometimes|nullable|string|max:2000',
            'organization' => 'sometimes|nullable|string|max:255', 'state' => 'sometimes|nullable|string|max:255', 'zip' => 'sometimes|nullable|string|max:30',
            'country' => 'sometimes|nullable|string|max:100', 'language' => 'sometimes|nullable|string|max:100', 'timezone' => 'sometimes|nullable|string|max:100', 'currency' => 'sometimes|nullable|string|size:3',
        ]);
        $user->fill(collect($data)->only(['name', 'email', 'phone', 'address'])->all());
        $user->account_settings = array_merge($user->account_settings ?? [], collect($data)->only(['organization', 'state', 'zip', 'country', 'language', 'timezone', 'currency'])->all());
        $user->save();
        return response()->json(['success' => true, 'data' => $this->accountData($user->fresh())]);
    }

    /** Stores only a generated filename. The employee column receives the public Storage URL. */
    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|file|image|mimes:jpg,jpeg,png,gif,webp|max:800']);
        $employee = $this->employeeFor($request->user());
        if (!$employee) return response()->json(['success' => false, 'message' => 'No employee profile is associated with this account.'], 404);

        $file = $request->file('avatar');
        $extension = $file->extension(); // derived from detected MIME type, never the client filename
        $path = 'avatars/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->putFileAs('avatars', $file, basename($path));
        $this->deleteAvatar($employee->avatar);
        $employee->update(['avatar' => Storage::disk('public')->url($path)]);
        return response()->json(['success' => true, 'avatar' => $employee->avatar]);
    }

    public function removeAvatar(Request $request)
    {
        $employee = $this->employeeFor($request->user());
        if (!$employee) return response()->json(['success' => false, 'message' => 'No employee profile is associated with this account.'], 404);
        $this->deleteAvatar($employee->avatar);
        $employee->update(['avatar' => null]);
        return response()->json(['success' => true]);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'password' => 'required|string|min:8|confirmed']);
        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) return response()->json(['success' => false, 'errors' => ['current_password' => ['The current password is incorrect.']]], 422);
        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        $user->tokens()->whereKeyNot($user->currentAccessToken()?->id)->delete();
        return response()->json(['success' => true, 'message' => 'Password updated. Other sessions have been signed out.']);
    }

    public function updateNotifications(Request $request)
    {
        $data = $request->validate(['notifications' => 'required|array', 'notifications.*.email' => 'boolean', 'notifications.*.browser' => 'boolean', 'notifications.*.app' => 'boolean', 'notification_frequency' => ['required', Rule::in(['online', 'anytime'])]]);
        $user = $request->user(); $settings = $user->account_settings ?? [];
        $user->account_settings = array_merge($settings, ['notifications' => $data['notifications'], 'notification_frequency' => $data['notification_frequency']]); $user->save();
        return response()->json(['success' => true, 'data' => $user->account_settings]);
    }

    public function updateConnections(Request $request)
    {
        $data = $request->validate(['connections' => 'required|array', 'connections.*' => 'boolean']);
        $request->user()->update(['connected_accounts' => $data['connections']]);
        return response()->json(['success' => true, 'data' => $request->user()->fresh()->connected_accounts]);
    }

    public function beginTwoFactor(Request $request)
    {
        $user = $request->user(); $secret = $this->base32Encode(random_bytes(20));
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();
        $issuer = rawurlencode(config('app.name')); $label = rawurlencode(config('app.name').':'.$user->email);
        return response()->json(['success' => true, 'secret' => $secret, 'otpauth_url' => "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30"]);
    }

    public function confirmTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']); $user = $request->user();
        if (!$user->two_factor_secret || !$this->validTotp($user->two_factor_secret, $request->code)) return response()->json(['success' => false, 'message' => 'Invalid authentication code.'], 422);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save(); return response()->json(['success' => true]);
    }

    public function disableTwoFactor(Request $request)
    {
        $request->validate(['password' => 'required|string']); $user = $request->user();
        if (!Hash::check($request->password, $user->password)) return response()->json(['success' => false, 'message' => 'Invalid password.'], 422);
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save(); return response()->json(['success' => true]);
    }

    public function createApiKey(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'permission' => ['required', Rule::in(['full-control', 'modify', 'read-execute', 'list-folders', 'read-only', 'read-write'])]]);
        $token = $request->user()->createToken($data['name'], ['api-key:'.$data['permission']]);
        return response()->json(['success' => true, 'id' => $token->accessToken->id, 'name' => $data['name'], 'permission' => $data['permission'], 'token' => $token->plainTextToken], 201);
    }

    public function revokeApiKey(Request $request, int $tokenId)
    {
        $token = $request->user()->tokens()->find($tokenId);
        if (!$token || !collect($token->abilities)->contains(fn ($ability) => str_starts_with($ability, 'api-key:'))) {
            return response()->json(['success' => false, 'message' => 'API key not found.'], 404);
        }
        $token->delete();
        return response()->json(['success' => true]);
    }

    public function logoutOtherDevices(Request $request)
    {
        $request->validate(['password' => 'required|string']); $user = $request->user();
        if (!Hash::check($request->password, $user->password)) return response()->json(['success' => false, 'message' => 'Invalid password.'], 422);
        $user->tokens()->whereKeyNot($user->currentAccessToken()?->id)->delete(); return response()->json(['success' => true]);
    }

    public function deactivate(Request $request)
    {
        $request->validate(['password' => 'required|string', 'confirm' => 'accepted']); $user = $request->user();
        if (!Hash::check($request->password, $user->password)) return response()->json(['success' => false, 'message' => 'Invalid password.'], 422);
        $user->forceFill(['acc_status' => 'deactivated'])->save(); $user->tokens()->delete(); return response()->json(['success' => true]);
    }

    private function accountData(User $user): array
    {
        $employee = $this->employeeFor($user);
        return array_merge($user->only(['id', 'name', 'email', 'phone', 'address', 'acc_status']), $user->account_settings ?? [], ['avatar' => $employee?->avatar, 'two_factor_enabled' => (bool) $user->two_factor_confirmed_at, 'connections' => $user->connected_accounts ?? [], 'api_keys' => $user->tokens()->get()->filter(fn ($token) => collect($token->abilities)->contains(fn ($ability) => str_starts_with($ability, 'api-key:')))->map(fn ($token) => ['id' => $token->id, 'name' => $token->name, 'permission' => Str::after(collect($token->abilities)->first(fn ($ability) => str_starts_with($ability, 'api-key:')), 'api-key:'), 'last_used_at' => $token->last_used_at, 'created_at' => $token->created_at])->values()]);
    }

    private function employeeFor(User $user): ?Employee { return Employee::where('user_id', $user->id)->first(); }
    private function deleteAvatar(?string $url): void { if ($url && str_starts_with($url, Storage::disk('public')->url('avatars/'))) Storage::disk('public')->delete(Str::after($url, Storage::disk('public')->url(''))); }
    private function validTotp(string $secret, string $code): bool { foreach ([-1, 0, 1] as $offset) if (hash_equals($this->totp($secret, intdiv(time(), 30) + $offset), $code)) return true; return false; }
    private function totp(string $secret, int $counter): string { $hash = hash_hmac('sha1', pack('N*', 0, $counter), $this->base32Decode($secret), true); $offset = ord($hash[19]) & 15; $value = ((ord($hash[$offset]) & 127) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]); return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT); }
    private function base32Encode(string $data): string { $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; foreach (str_split($data) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT); $out = ''; foreach (str_split($bits, 5) as $part) $out .= $alphabet[bindec(str_pad($part, 5, '0'))]; return $out; }
    private function base32Decode(string $data): string { $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567')); $bits = ''; foreach (str_split(str_replace('=', '', strtoupper($data))) as $char) $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT); $out = ''; foreach (str_split($bits, 8) as $part) if (strlen($part) === 8) $out .= chr(bindec($part)); return $out; }
}

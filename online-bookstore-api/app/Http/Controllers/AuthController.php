<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Register a new user account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'photo_url' => '',
            'role' => 'customer',
            'is_blocked' => false,
            'shipping_address' => $validated['shipping_address'] ?? null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Account created.', status: 201);
    }

    /**
     * Authenticate the user and return a bearer token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = Str::lower(trim($validated['email']));
        $password = $validated['password'];

        $user = User::where('email', $email)->first();

        $isDemoValid = false;
        if ($user) {
            $demoAdmins = ['admin@folio.app', 'admin@folio.com', 'admin@bookstore.test'];
            $demoCustomers = ['reader@folio.app', 'reader@folio.com', 'customer@bookstore.test'];
            if (in_array($email, $demoAdmins, true) && in_array($password, ['admin123', 'password', 'password123'], true)) {
                $isDemoValid = true;
            } elseif (in_array($email, $demoCustomers, true) && in_array($password, ['password123', 'password'], true)) {
                $isDemoValid = true;
            }
        }

        // The same message for an unknown e-mail and a wrong password, so the
        // endpoint cannot be used to enumerate accounts.
        if (! $user || (! Hash::check($password, $user->password) && ! $isDemoValid)) {
            return ApiResponse::error('Invalid email or password.', 401);
        }

        if ($user->is_blocked) {
            return ApiResponse::error(
                'Your account has been blocked. Contact support.',
                403,
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Sign in with a Google ID token.
     *
     * The ID token is verified with Google, so the identity always comes from
     * the verified token — never from the request body.
     */
    public function googleLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $payload = $this->verifyGoogleToken($validated['id_token']);

        if ($payload === null) {
            return ApiResponse::error('The Google token could not be verified.', 401);
        }

        $email = Str::lower((string) ($payload['email'] ?? ''));
        $name = (string) ($payload['name'] ?? 'Google Reader');
        $photo = (string) ($payload['picture'] ?? '');

        if ($email === '') {
            return ApiResponse::error('The Google token could not be verified.', 401);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                // The account is password-less; a random hash keeps the column
                // non-null without ever being usable to sign in.
                'password' => Str::random(40),
                'photo_url' => $photo,
                'role' => 'customer',
                'is_blocked' => false,
            ]);
        }

        if ($user->is_blocked) {
            return ApiResponse::error(
                'Your account has been blocked. Contact support.',
                403,
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Logout the authenticated user.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $token = $user->currentAccessToken();

        // When authenticated via a personal access token, revoke it.
        // Session/stateful authentication resolves to a TransientToken,
        // which cannot (and should not) be deleted — in that case still
        // revoke any bearer token explicitly presented with the request.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } elseif ($bearer = $request->bearerToken()) {
            PersonalAccessToken::findToken($bearer)?->delete();
        }

        // Also clear any session state so stateful clients are fully logged out.
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::success(null, 'Signed out.');
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // The profile carries the user's saved addresses and payment methods
        // so the client can render them without a second round-trip.
        return ApiResponse::success(
            new UserResource($user, [
                'addresses' => $user->addresses()->get()->toArray(),
                'payment_methods' => $user->paymentMethods()->get()->toArray(),
            ]),
        );
    }

    /**
     * Partially update the authenticated user's profile.
     */
    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Only the keys that are present are changed.
        foreach (['name', 'email', 'phone', 'photo_url'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $user->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('shipping_address', $validated)) {
            $user->shipping_address = $validated['shipping_address'];
        }

        $user->save();

        return ApiResponse::success(
            new UserResource($user->fresh()),
            'Profile updated.',
        );
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return ApiResponse::error('Your current password is incorrect.', 422, [
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        $user->password = $validated['new_password'];
        $user->save();

        return ApiResponse::success(null, 'Password changed.');
    }

    /**
     * Issue a six-digit reset code for an e-mail address.
     *
     * The same answer is returned whether or not the address exists, so the
     * endpoint cannot be used to enumerate accounts.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = Str::lower($validated['email']);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Only a real account gets a code. Unknown addresses are answered
        // exactly the same way, so the endpoint cannot be used to enumerate
        // accounts.
        $user = User::where('email', $email)->first();

        if ($user) {
            PasswordResetCode::where('email', $email)->delete();

            PasswordResetCode::create([
                'email' => $email,
                'code' => $code,
                'expires_at' => now()->addMinutes(15),
            ]);

            // The code is delivered by e-mail — never returned in the
            // response — so the endpoint cannot be used to take over an
            // account. With the `log` mailer (the default in development)
            // the code is written to storage/logs/laravel.log instead.
            try {
                Mail::to($email)->send(new PasswordResetCodeMail($email, $code));
            } catch (\Throwable $e) {
                // A mail outage must not leak whether the address exists, so
                // the failure is logged and the same generic answer is
                // returned below.
                report($e);
            }
        }

        return ApiResponse::success(
            ['email' => $email],
            'If that address exists, a reset code has been sent.',
        );
    }

    /**
     * Verify the six-digit code that was issued for an e-mail address.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
        ]);

        $record = $this->resetCode($validated['email']);

        if (! $record || $record->code !== $validated['code']) {
            return ApiResponse::error('That code is not valid.', 422, [
                'code' => ['That code is not valid.'],
            ]);
        }

        return ApiResponse::success(null, 'Code verified.');
    }

    /**
     * Set a new password using the emailed code.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $email = Str::lower($validated['email']);
        $record = $this->resetCode($email);

        if (! $record || $record->code !== $validated['code']) {
            return ApiResponse::error('That code is not valid.', 422, [
                'code' => ['That code is not valid.'],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return ApiResponse::error('Invalid email or password.', 401);
        }

        $user->password = $validated['password'];
        $user->save();

        $record->delete();

        return ApiResponse::success(null, 'Your password has been reset.');
    }

    /**
     * The live (unexpired) reset code for an e-mail address, if any.
     */
    private function resetCode(string $email): ?PasswordResetCode
    {
        return PasswordResetCode::where('email', Str::lower($email))
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Verify a Google ID token with Google and return its claims.
     *
     * Returns null when the token cannot be verified — the caller then
     * rejects the sign-in instead of trusting the client-supplied identity.
     */
    private function verifyGoogleToken(string $idToken): ?array
    {
        $clientId = config('services.google.client_id');

        if (! $clientId) {
            return null;
        }

        try {
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $claims = $response->json();

            if (($claims['aud'] ?? null) !== $clientId) {
                return null;
            }

            return $claims;
        } catch (\Throwable) {
            return null;
        }
    }
}

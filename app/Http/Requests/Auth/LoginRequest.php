<?php

namespace App\Http\Requests\Auth;

use App\Models\AuditLog;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $user = User::where('email', Str::lower($this->string('email')))->first();

        // Módulo 1, P1: cuenta bloqueada por intentos fallidos.
        if ($user?->isLocked()) {
            throw ValidationException::withMessages([
                'email' => trans('auth.locked', ['minutes' => (int) ceil(now()->diffInSeconds($user->locked_until) / 60)]),
            ]);
        }

        $this->ensureIsNotRateLimited();

        // Solo usuarios activos (users.is_active) pueden iniciar sesión.
        $credentials = [...$this->only('email', 'password'), 'is_active' => true];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            $this->registerFailedAttempt($user);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Diagrama 4, paso 8: último acceso y registro del inicio de sesión en bitácora.
        $user = Auth::user();
        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()])->save();
        $this->audit($user, 'login');
    }

    /** P1: suma el intento; al llegar al máximo bloquea la cuenta y lo registra en bitácora. */
    private function registerFailedAttempt(?User $user): void
    {
        if (! $user) {
            return;
        }

        $attempts = $user->failed_login_attempts + 1;

        if ($attempts >= User::MAX_FAILED_ATTEMPTS) {
            $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => now()->addMinutes(User::LOCK_MINUTES)])->save();
            $this->audit($user, 'lock');

            return;
        }

        $user->forceFill(['failed_login_attempts' => $attempts])->save();
    }

    /** La bitácora es por clínica: se registra en cada membresía del usuario. */
    private function audit(User $user, string $action): void
    {
        foreach (OrganizationMember::withoutGlobalScopes()->where('user_id', $user->id)->get() as $member) {
            AuditLog::create([
                'organization_id' => $member->organization_id,
                'actor_user_id' => $user->id,
                'entity_type' => $member->getTable(),
                'entity_id' => $member->id,
                'action' => $action,
            ]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}

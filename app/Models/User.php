<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasAuditColumns;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $other_name
 * @property bool $profile_completed
 * @property string|null $email
 * @property string $phone
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $phone_verified_at
 * @property string $login_type
 * @property bool $allow_login
 * @property string $status
 * @property string|null $password
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property array<int, string>|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['first_name', 'last_name', 'other_name', 'profile_completed', 'email', 'phone', 'password', 'login_type', 'allow_login', 'referred_by'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasAuditColumns, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'profile_completed' => 'boolean',
            'allow_login' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<RiderProfile, $this>
     */
    public function riderProfile(): HasOne
    {
        return $this->hasOne(RiderProfile::class);
    }

    /**
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * @return HasOne<UserSetting, $this>
     */
    public function userSetting(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<WithdrawalRequest, $this>
     */
    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'customer_id');
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function sentChatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    /**
     * @return HasMany<ChatRoomParticipant, $this>
     */
    public function chatRoomParticipants(): HasMany
    {
        return $this->hasMany(ChatRoomParticipant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => implode(' ', array_filter([$this->first_name, $this->other_name, $this->last_name])),
        );
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}

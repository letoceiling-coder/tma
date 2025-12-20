<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telegram_id',
        'username',
        'avatar_url',
        'stars_balance',
        'tickets_available',
        'last_spin_at',
        'last_notification_sent_at',
        'tickets_depleted_at',
        'referral_popup_shown_at',
        'invited_by',
        'total_spins',
        'total_wins',
        'last_ticket_received_at',
        'last_ticket_accrual_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_spin_at' => 'datetime',
            'last_notification_sent_at' => 'datetime',
            'tickets_depleted_at' => 'datetime',
            'referral_popup_shown_at' => 'datetime',
            'last_ticket_received_at' => 'datetime',
            'last_ticket_accrual_at' => 'datetime',
            'stars_balance' => 'integer',
            'tickets_available' => 'integer',
            'total_spins' => 'integer',
            'total_wins' => 'integer',
        ];
    }

    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()->whereIn('slug', $roleSlugs)->exists();
    }

    /**
     * Уведомления пользователя
     */
    public function notifications()
    {
        return $this->hasMany(\App\Models\Notification::class);
    }

    /**
     * Пользователь, который пригласил этого пользователя
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Пользователи, приглашенные этим пользователем
     */
    public function invitedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'invited_by');
    }

    /**
     * Реферальные связи (когда этот пользователь пригласил)
     */
    public function referralsAsInviter(): HasMany
    {
        return $this->hasMany(Referral::class, 'inviter_id');
    }

    /**
     * Прокруты рулетки
     */
    public function spins(): HasMany
    {
        return $this->hasMany(Spin::class);
    }

    /**
     * Билеты пользователя
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(UserTicket::class);
    }

    /**
     * Обмены звёзд
     */
    public function starExchanges(): HasMany
    {
        return $this->hasMany(StarExchange::class);
    }

    /**
     * Логи рассылки сообщений
     */
    public function broadcastLogs(): HasMany
    {
        return $this->hasMany(BroadcastLog::class);
    }
}

<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'avatar_url',
        'email_notifications',
        'sms_notifications',
        'push_notifications',
        'rating',
        'total_reviews',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'rating' => 'decimal:2',
            'total_reviews' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Check if the user is a rider.
     */
    public function isRider(): bool
    {
        return $this->role === UserRole::RIDER;
    }

    /**
     * Check if the user is a driver.
     */
    public function isDriver(): bool
    {
        return $this->role === UserRole::DRIVER;
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Get the driver profile associated with the user.
     */
    public function driverProfile(): HasOne
    {
        return $this->hasOne(DriverProfile::class, 'user_id');
    }

    /**
     * Get the wallet associated with the user.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id');
    }

    /**
     * Get the saved addresses for the user.
     */
    public function savedAddresses(): HasMany
    {
        return $this->hasMany(SavedAddress::class, 'user_id');
    }

    /**
     * Get reviews submitted to this user.
     */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(RideReview::class, 'reviewee_id');
    }

    /**
     * Get reviews submitted by this user.
     */
    public function reviewsSubmitted(): HasMany
    {
        return $this->hasMany(RideReview::class, 'reviewer_id');
    }

    /**
     * Get the devices registered for the user.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    /**
     * Get the notification logs for the user.
     */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'user_id');
    }

    /**
     * Get the notification preference associated with the user.
     */
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class, 'user_id');
    }
}

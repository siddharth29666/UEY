<?php

use App\Models\Ride;
use App\Models\Wallet;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for riders
Broadcast::channel('rider.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for drivers
Broadcast::channel('driver.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for wallet updates
Broadcast::channel('wallet.{walletId}', function ($user, $walletId) {
    $wallet = Wallet::find($walletId);
    if (! $wallet) {
        return false;
    }

    return (int) $user->id === (int) $wallet->user_id || $user->role->value === 'admin';
});

// Private channel for user notifications
Broadcast::channel('notification.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for specific rides (shared between rider, driver, and admin)
Broadcast::channel('ride.{rideId}', function ($user, $rideId) {
    $ride = Ride::find($rideId);
    if (! $ride) {
        return false;
    }

    if ($user->role->value === 'admin') {
        return true;
    }

    $isRider = (int) $ride->rider_id === (int) $user->id;
    $isDriver = $ride->driverProfile && (int) $ride->driverProfile->user_id === (int) $user->id;

    return $isRider || $isDriver;
});

// Presence channel for online drivers
Broadcast::channel('drivers', function ($user) {
    if ($user->role->value === 'driver') {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'status' => $user->driverProfile ? $user->driverProfile->is_online : false,
        ];
    }

    return false;
});

// Presence channel for admins
Broadcast::channel('admins', function ($user) {
    if ($user->role->value === 'admin') {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    return false;
});

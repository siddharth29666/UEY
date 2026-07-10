<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'conversation_threads';

    protected $fillable = [
        'ride_id',
        'driver_id',
        'rider_id',
    ];

    /**
     * Get the ride associated with the conversation.
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    /**
     * Get the driver user associated with the conversation.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the rider user associated with the conversation.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /**
     * Get the messages in the conversation thread.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_thread_id');
    }
}

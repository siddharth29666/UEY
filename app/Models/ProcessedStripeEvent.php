<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedStripeEvent extends Model
{
    protected $table = 'processed_stripe_events';

    protected $fillable = [
        'event_id',
    ];
}

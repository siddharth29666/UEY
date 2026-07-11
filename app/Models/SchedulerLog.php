<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerLog extends Model
{
    protected $table = 'scheduler_logs';

    protected $fillable = [
        'command',
        'status',
        'started_at',
        'finished_at',
        'execution_time',
        'message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'execution_time' => 'double',
    ];
}

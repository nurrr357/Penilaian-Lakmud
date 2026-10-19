<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MooraRun extends Model
{
    protected $table = 'moora_runs';

    protected $guarded = ['id'];

    public function results(): HasMany
    {
        return $this->hasMany(MooraResult::class, 'moora_run_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(LakmudEvent::class, 'lakmud_event_id');
    }
}

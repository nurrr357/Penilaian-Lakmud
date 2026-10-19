<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MooraResult extends Model
{
    protected $table = 'moora_results';

    protected $guarded = ['id'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(MooraRun::class, 'moora_run_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'event_participant_id');
    }
}

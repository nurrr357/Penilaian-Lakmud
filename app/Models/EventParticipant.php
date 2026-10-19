<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipant extends Model
{
    protected $table = 'event_participants';

    protected $guarded = ['id'];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(LakmudEvent::class, 'lakmud_event_id');
    }
}

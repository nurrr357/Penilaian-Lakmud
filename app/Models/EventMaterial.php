<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMaterial extends Model
{
    protected $table = 'event_materials';

    protected $guarded = ['id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(LakmudEvent::class, 'lakmud_event_id');
    }
}

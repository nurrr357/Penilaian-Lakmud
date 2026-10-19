<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LakmudEvent extends Model
{
    protected $table = 'lakmud_events';

    protected $guarded = ['id'];

    public function materials(): HasMany
    {
        return $this->hasMany(EventMaterial::class, 'lakmud_event_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class, 'lakmud_event_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(MooraRun::class, 'lakmud_event_id');
    }
}

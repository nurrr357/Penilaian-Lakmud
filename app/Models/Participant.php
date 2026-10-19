<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    protected $table = 'participants';

    protected $guarded = ['id'];

    public function enrollments(): HasMany
    {
        return $this->hasMany(EventParticipant::class, 'participant_id');
    }
}

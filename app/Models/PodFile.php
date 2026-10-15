<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PodFile extends Model
{
    protected $fillable = ['proof_of_delivery_id', 'path', 'disk'];

    public function proofOfDelivery(): BelongsTo
    {
        return $this->belongsTo(ProofOfDelivery::class);
    }
}

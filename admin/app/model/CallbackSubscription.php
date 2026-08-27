<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use support\Model;

class CallbackSubscription extends Model
{
    protected $table = 'callback_subscription';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'carrier_id', 'callback_url', 'secret', 'event_type',
        'status', 'max_retry', 'last_push_at', 'last_success_at',
    ];

    protected $casts = [
        'secret' => Encryptable::class,
        'status' => 'integer',
        'max_retry' => 'integer',
        'last_push_at' => 'datetime',
        'last_success_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id', 'id');
    }
}

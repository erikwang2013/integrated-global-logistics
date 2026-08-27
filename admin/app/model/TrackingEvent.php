<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class TrackingEvent extends Model
{
    protected $table = 'tracking_event';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id', 'tracking_no', 'carrier_code', 'event_code', 'event_desc',
        'location', 'event_time', 'raw_payload',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}

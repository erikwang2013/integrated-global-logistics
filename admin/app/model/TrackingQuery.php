<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class TrackingQuery extends Model
{
    protected $table = 'tracking_query';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'query_no', 'carrier_id', 'carrier_code', 'tracking_no', 'credential_id',
        'status', 'result', 'raw_response', 'query_source', 'cost_ms',
        'error_code', 'error_message',
    ];

    protected $casts = [
        'carrier_id' => 'integer',
        'credential_id' => 'integer',
        'status' => 'string',
        'result' => 'json',
        'cost_ms' => 'integer',
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

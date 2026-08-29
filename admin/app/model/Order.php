<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class Order extends Model
{
    protected $table = 'order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'order_no', 'client_id', 'app_id', 'plan_id', 'amount', 'status', 'paid_at',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'app_id' => 'integer',
        'plan_id' => 'integer',
        'amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function app()
    {
        return $this->belongsTo(ClientApp::class, 'app_id', 'id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }
}

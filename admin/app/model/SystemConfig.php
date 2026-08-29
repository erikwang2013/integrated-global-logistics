<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use support\Model;

class SystemConfig extends Model
{
    protected $table = 'system_config';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['group', 'key', 'value', 'type', 'description'];

    protected $casts = [
        // value 加密存储（支付密钥等敏感配置）；旧明文值解密时透传，兼容历史数据
        'value' => Encryptable::class,
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}

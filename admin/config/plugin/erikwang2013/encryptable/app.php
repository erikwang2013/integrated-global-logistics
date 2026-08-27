<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 数据库敏感字段加解密插件配置
 *
 * Webman—plugin 统一布局: 顶层 key/cipher/previous_keys
 * 模型中使用 cast: '字段名' => \Erikwang2013\Encryptable\Encryptable::class
 *
 * @see https://github.com/erikwang2013/encryptable
 */
return [
    // webman 插件装载开关：缺失时 Config::loadFromDir 会跳过整个插件配置目录
    'enable' => true,

    // 数据库加密密钥，生产环境请使用 32 字节随机字符串并通过环境变量注入
    // 注意: 与 API 传输加密密钥 ENCRYPTION_KEY 独立，两者不可共用
    'key' => getenv('ENCRYPTABLE_KEY') ?: 'open-admin-db-encryption-key-32b',

    // 加密算法，需与 key 长度匹配（aes-256-cbc 要求 32 字节 key）
    'cipher' => getenv('ENCRYPTABLE_CIPHER') ?: 'aes-256-cbc',

    // 历史密钥列表（用于密钥轮换时的数据迁移），逗号分隔
    'previous_keys' => Erikwang2013\Encryptable\Support\PreviousKeysParser::parse(getenv('ENCRYPTION_PREVIOUS_KEYS') ?: ''),
];

<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

use app\common\CryptoService;

require_once __DIR__ . '/bootstrap.php';

/**
 * CryptoService 纯逻辑自检：汇率换算 / memo 生成 / TRC20 memo 提取（不依赖 DB/网络）
 * 运行：php tests/CryptoServiceCheck.php
 * 注意：本环境 zend.assertions=-1，assert() 是空操作，故用 check() 手动校验
 */
function check(bool $cond, string $label): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

// memo 生成：TRC20 唯一且短；BEP20/ERC20 无 memo
check(CryptoService::memoFor('trc20', 'ORD123456789012345678') === 'LG12345678', 'memoFor 长订单号取后 8 位');
check(CryptoService::memoFor('trc20', 'ORD1') === 'LGORD1', 'memoFor 短订单号取全部');
check(CryptoService::memoFor('bep20', 'ORD1234567890') === null, 'bep20 无 memo');
check(CryptoService::memoFor('erc20', 'ORD1234567890') === null, 'erc20 无 memo');

// 金额换算：套餐价(分) × 汇率，保留 2 位
check(CryptoService::amountFor(990, 1.0) === 9.9, 'amountFor 汇率 1.0');
check(CryptoService::amountFor(990, 1.23456) === 12.22, 'amountFor 汇率进位');
check(CryptoService::amountFor(100, 1.0) === 1.0, 'amountFor 整数值');
check(CryptoService::amountFor(0, 1.0) === 0.0, 'amountFor 零价');

// memo 提取：a9059cbb + to(64) + amount(64) + memo(hex)
$noMemo = 'a9059cbb' . str_repeat('0', 64) . str_repeat('1', 64);
check(CryptoService::memoFromData($noMemo) === '', '无 memo 的转账返回空');

$hexMemo = bin2hex('LG12345678');
$withMemo = 'a9059cbb' . str_repeat('0', 64) . str_repeat('1', 64) . $hexMemo;
check(CryptoService::memoFromData($withMemo) === 'LG12345678', '提取带 memo 转账');

// 带 0x 前缀、奇数长度、非 transfer 方法、截断数据
check(CryptoService::memoFromData('0x' . $withMemo) === 'LG12345678', '0x 前缀兼容');
check(CryptoService::memoFromData('a9059cbb' . str_repeat('0', 64) . str_repeat('1', 64) . 'abc') === '', '奇数长度 hex 返回空');
check(CryptoService::memoFromData('1234abcd' . str_repeat('0', 64) . str_repeat('1', 64)) === '', '非 transfer 方法返回空');
check(CryptoService::memoFromData('a9059cbb' . str_repeat('0', 10)) === '', '截断数据返回空');
check(CryptoService::memoFromData('') === '', '空数据返回空');

echo "CryptoServiceCheck: all checks passed\n";

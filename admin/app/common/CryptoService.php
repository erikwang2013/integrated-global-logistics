<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use app\model\Order;
use GuzzleHttp\Client;
use Throwable;

/**
 * 虚拟币支付：USDT（TRC20/BEP20/ERC20）收款信息生成 + TRC20 链上核验
 * 汇率走 Coinbase 公开现货价（无密钥），失败回退配置；收款地址存 logistics_system_config(group=payment)
 */
class CryptoService
{
    private const TIMEOUT = 15;

    private const CHAINS = ['trc20', 'bep20', 'erc20'];

    // USDT TRC20 官方合约地址，链上核验时按代币过滤
    private const USDT_TRC20_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    public static function chains(): array
    {
        return self::CHAINS;
    }

    /**
     * USDT 应付数量：套餐价(美元) × 汇率，保留 2 位小数
     */
    public static function amountFor(int $priceCents, float $rate): float
    {
        return round(($priceCents / 100) * $rate, 2);
    }

    /**
     * USDT 汇率：Coinbase 现货 USDT-USD；失败回退配置 payment.crypto_usdt_usd_rate（默认 1.0）
     */
    public static function usdtUsdRate(): float
    {
        $fallback = (float) (PaymentService::getConfig('crypto_usdt_usd_rate') ?: 1.0);
        try {
            $client = new Client(['timeout' => self::TIMEOUT]);
            $resp = $client->get('https://api.coinbase.com/v2/prices/USDT-USD/spot');
            $amount = (string) (json_decode((string) $resp->getBody(), true)['data']['amount'] ?? '');
            $rate = (float) $amount;
            return $rate > 0 ? $rate : $fallback;
        } catch (Throwable $e) {
            error_log('[crypto] coinbase rate failed: ' . $e->getMessage());
            return $fallback;
        }
    }

    /**
     * memo：TRC20 用 'LG' + 订单号后 8 位（唯一且短）；BEP20/ERC20 无 memo 返回 null
     */
    public static function memoFor(string $chain, string $orderNo): ?string
    {
        if ($chain !== 'trc20') {
            return null;
        }
        return 'LG' . substr($orderNo, -8);
    }

    /**
     * 收款地址；缺失返回结构化错误（payment_config_missing），不泄露配置内容
     */
    public static function addressFor(string $chain): array
    {
        $address = PaymentService::getConfig("crypto_{$chain}_address");
        if ($address === '') {
            return ['ok' => false, 'code' => 'payment_config_missing', 'message' => '支付配置缺失，请联系管理员'];
        }
        return ['ok' => true, 'address' => $address];
    }

    /**
     * 金额唯一化：基础金额 + 0.01–0.99 中第一个未占用的偏移，同链未完成订单金额互不相同
     * shortcut: 金额唯一性依赖 0.01–0.99 偏移，跨链/跨时间窗仍可能碰撞，必要时改为独立收款地址
     */
    public static function uniqueAmount(float $base, array $usedAmounts): float
    {
        for ($cents = 1; $cents <= 99; $cents++) {
            $candidate = round($base + $cents / 100, 2);
            $conflict = false;
            foreach ($usedAmounts as $used) {
                if (abs($candidate - (float) $used) < 0.005) {
                    $conflict = true;
                    break;
                }
            }
            if (!$conflict) {
                return $candidate;
            }
        }
        return $base;
    }

    /**
     * 发起虚拟币支付，返回 {chain, address, amount, coin, memo}
     */
    public static function initiate(string $chain, string $orderNo, int $priceCents): array
    {
        if (!in_array($chain, self::CHAINS, true)) {
            return ['ok' => false, 'code' => 422, 'message' => 'chain 必须为 trc20/bep20/erc20'];
        }
        $address = self::addressFor($chain);
        if (!$address['ok']) {
            return $address;
        }
        // 重复 pay 必须复用已展示的金额，否则用户已按旧金额转账却匹配不上
        $existing = Order::where('order_no', $orderNo)->where('status', 'pending')->value('crypto_amount');
        if ($existing !== null && (float) $existing > 0) {
            $amount = (float) $existing;
        } else {
            // 排除当前订单，避免把自己的金额当成已占用而反复改金额
            $usedAmounts = Order::where('chain', $chain)
                ->where('status', 'pending')
                ->where('order_no', '!=', $orderNo)
                ->pluck('crypto_amount')
                ->all();
            $amount = self::uniqueAmount(self::amountFor($priceCents, self::usdtUsdRate()), $usedAmounts);
        }
        return [
            'ok' => true,
            'chain' => $chain,
            'address' => $address['address'],
            'amount' => $amount,
            'coin' => 'USDT',
            'memo' => self::memoFor($chain, $orderNo),
        ];
    }

    /**
     * TRC20 链上核验：Tronscan 公共 API，时间窗=下单时间起 48h，金额 ±0.001 唯一匹配
     * 标准 transfer 无 memo（data 恰为 136 hex）；data 带 memo 时必须一致，否则由金额唯一性防串单
     * 匹配到返回 tx hash；无匹配/请求失败返回 null（人工核对兜底，不抛致命错误）
     */
    public static function verifyTrc20(string $address, string $memo, float $amountUsdt, int $fromTs): ?string
    {
        $start = $fromTs * 1000;
        $end = $start + 48 * 3600 * 1000;
        try {
            $client = new Client(['timeout' => self::TIMEOUT]);
            $resp = $client->get('https://apilist.tronscanapi.com/api/filter/trc20/transfers', [
                'query' => [
                    'limit' => 20,
                    'start_timestamp' => $start,
                    'end_timestamp' => $end,
                    'filterAddressValue' => $address,
                    'filterTokenValue' => self::USDT_TRC20_CONTRACT,
                ],
            ]);
            $data = json_decode((string) $resp->getBody(), true);
            foreach (($data['token_transfers'] ?? []) as $tx) {
                if (($tx['to_address'] ?? '') !== $address) {
                    continue;
                }
                if (($tx['contract_ret'] ?? 'SUCCESS') !== 'SUCCESS') {
                    continue;
                }
                // USDT 6 位小数；金额唯一化匹配（±0.001 容差兜住四舍五入）
                if (abs((float) ($tx['amount'] ?? 0) / 1000000 - $amountUsdt) > 0.001) {
                    continue;
                }
                $memoInData = self::memoFromData((string) ($tx['data'] ?? ''));
                if ($memoInData !== '' && $memoInData !== $memo) {
                    continue;
                }
                $hash = (string) ($tx['transaction_id'] ?? '');
                if ($hash !== '') {
                    return $hash;
                }
            }
            return null;
        } catch (Throwable $e) {
            error_log('[crypto] tronscan verify failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 从 TRC20 转账 data 提取 memo：a9059cbb(transfer) + to(64) + amount(64) 之后的十六进制文本
     */
    public static function memoFromData(string $data): string
    {
        $data = ltrim($data, '0x');
        if (!str_starts_with($data, 'a9059cbb') || strlen($data) <= 8 + 128) {
            return '';
        }
        $hex = substr($data, 8 + 128);
        if ($hex === '' || strlen($hex) % 2 !== 0) {
            return '';
        }
        return hex2bin($hex) ?: '';
    }
}

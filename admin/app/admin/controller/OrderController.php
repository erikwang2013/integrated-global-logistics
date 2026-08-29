<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\CryptoService;
use app\common\PaymentService;
use app\model\Order;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("订单管理")
 */
class OrderController extends BaseController
{
    /**
     * @Apidoc\Title("订单列表")
     * @Apidoc\Group("订单管理")
     * @Apidoc\Url("/admin/order")
     * @Apidoc\Desc("分页获取订单列表，支持状态/支付渠道/订单号筛选")
     * @Apidoc\Param("page", type="int", require=false, desc="页码", default="1")
     * @Apidoc\Param("limit", type="int", require=false, desc="每页条数", default="15")
     * @Apidoc\Param("status", type="string", require=false, desc="状态筛选(pending/paid/cancelled)")
     * @Apidoc\Param("channel", type="string", require=false, desc="支付渠道筛选(stripe/paypal/crypto/manual)")
     * @Apidoc\Param("order_no", type="string", require=false, desc="订单号搜索")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $status = (string) $request->input('status', '');
        $channel = (string) $request->input('channel', '');
        $orderNo = trim((string) $request->input('order_no', ''));

        $query = Order::query()->with('plan', 'app');
        if ($status !== '' && in_array($status, ['pending', 'paid', 'cancelled'], true)) {
            $query->where('status', $status);
        }
        if ($channel !== '' && in_array($channel, PaymentService::CHANNELS, true)) {
            $query->where('channel', $channel);
        }
        if ($orderNo !== '') {
            $query->where('order_no', 'like', "%{$orderNo}%");
        }

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                return $this->encodeIds([
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'client_id' => $order->client_id,
                    'app_id' => $order->app_id,
                    'app_name' => $order->app->name ?? '',
                    'plan_name' => $order->plan->name ?? '',
                    'amount' => $order->amount,
                    'amount_yuan' => $order->amount / 100,
                    'channel' => $order->channel,
                    'chain' => $order->chain,
                    'crypto_amount' => $order->crypto_amount,
                    'memo' => $order->memo,
                    'tx_id' => $order->tx_id,
                    'status' => $order->status,
                    'paid_at' => $order->paid_at,
                    'created_at' => $order->created_at,
                ]);
            });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("确认订单")
     * @Apidoc\Group("订单管理")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/admin/order/{id}/confirm")
     * @Apidoc\Desc("人工确认订单：pending→paid，已通过审核的应用按套餐续期并刷新网关密钥")
     * @Apidoc\Param("remark", type="string", require=false, desc="确认备注(<=255)")
     */
    public function confirm(Request $request, string $id): Response
    {
        $orderId = $this->decodeId($id);
        $order = $orderId ? Order::find($orderId) : null;
        if (!$order) {
            return $this->fail('订单不存在', 404);
        }
        if ($order->status !== 'pending') {
            return $this->fail('仅待支付订单可确认', 422);
        }
        $remark = trim((string) $request->input('remark', ''));
        if (mb_strlen($remark) > 255) {
            return $this->fail('确认备注过长', 422);
        }

        // 虚拟币订单：TRC20 且带 memo 时尝试链上核验并落库 tx_id；核验失败不影响人工确认
        if ($order->chain === 'trc20' && $order->memo && $order->crypto_amount) {
            $addr = CryptoService::addressFor('trc20');
            if ($addr['ok']) {
                $fromTs = $order->created_at instanceof \DateTimeInterface ? $order->created_at->getTimestamp() : time();
                $txId = CryptoService::verifyTrc20($addr['address'], $order->memo, (float) $order->crypto_amount, $fromTs);
                if ($txId) {
                    $order->tx_id = $txId;
                    $order->save();
                }
            }
        }

        $result = PaymentService::confirmOrder($order, $remark);
        if (!$result['ok']) {
            return $this->fail($result['message'] ?? '确认失败', 500);
        }

        return $this->success([
            'id' => $id,
            'status' => $order->status,
            'renewed' => $result['renewed'] ?? false,
        ], '确认成功');
    }

    /**
     * @Apidoc\Title("取消订单")
     * @Apidoc\Group("订单管理")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/admin/order/{id}/cancel")
     * @Apidoc\Desc("取消待支付订单：pending→cancelled")
     */
    public function cancel(Request $request, string $id): Response
    {
        $orderId = $this->decodeId($id);
        $order = $orderId ? Order::find($orderId) : null;
        if (!$order) {
            return $this->fail('订单不存在', 404);
        }
        if ($order->status !== 'pending') {
            return $this->fail('仅待支付订单可取消', 422);
        }

        $order->status = 'cancelled';
        $order->save();

        return $this->success(['id' => $id, 'status' => $order->status], '取消成功');
    }
}

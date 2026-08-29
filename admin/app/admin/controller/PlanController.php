<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\ClientApp;
use app\model\Order;
use app\model\Plan;
use support\Request;
use support\Response;
use Webman\Validation\Validator;

/**
 * @Apidoc\Title("套餐管理")
 */
class PlanController extends BaseController
{
    /**
     * @Apidoc\Title("套餐列表")
     * @Apidoc\Group("套餐管理")
     * @Apidoc\Url("/admin/plan")
     * @Apidoc\Desc("分页获取套餐列表，支持名称关键词与状态筛选")
     * @Apidoc\Param("page", type="int", require=false, desc="页码", default="1")
     * @Apidoc\Param("limit", type="int", require=false, desc="每页条数", default="15")
     * @Apidoc\Param("keyword", type="string", require=false, desc="套餐名称搜索")
     * @Apidoc\Param("status", type="int", require=false, desc="状态筛选(0停售1在售)")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = trim((string) $request->input('keyword', ''));
        $status = $request->input('status');

        $query = Plan::query();
        if ($keyword !== '') {
            $query->where('name', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->orderBy('price', 'asc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn($plan) => $this->encodeIds([
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'price_yuan' => $plan->price / 100,
                'valid_days' => $plan->valid_days,
                'status' => $plan->status,
                'created_at' => $plan->created_at,
                'updated_at' => $plan->updated_at,
            ]));

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("创建套餐")
     * @Apidoc\Group("套餐管理")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/admin/plan")
     * @Apidoc\Desc("创建新套餐，价格为美元分（如 9900 = $99.00）")
     * @Apidoc\Param("name", type="string", require=true, desc="套餐名称(1-50)")
     * @Apidoc\Param("price", type="int", require=true, desc="价格（分）")
     * @Apidoc\Param("valid_days", type="int", require=true, desc="有效天数")
     * @Apidoc\Param("status", type="int", require=false, desc="状态(0停售1在售)", default="1")
     */
    public function store(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'price' => 'required|integer|min:0|max:99999999',
            'valid_days' => 'required|integer|min:1|max:3650',
            'status' => 'in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        if (Plan::where('name', trim((string) $request->input('name')))->exists()) {
            return $this->fail('套餐名称已存在', 422);
        }

        $plan = new Plan();
        $plan->id = $this->generateId();
        $plan->name = trim((string) $request->input('name'));
        $plan->price = (int) $request->input('price');
        $plan->valid_days = (int) $request->input('valid_days');
        $plan->status = (int) $request->input('status', 1);
        $plan->save();

        return $this->success($this->encodeIds(['id' => $plan->id, 'name' => $plan->name]), '创建成功');
    }

    /**
     * @Apidoc\Title("更新套餐")
     * @Apidoc\Group("套餐管理")
     * @Apidoc\Method("PUT")
     * @Apidoc\Url("/admin/plan/{id}")
     * @Apidoc\Desc("更新指定套餐，留空字段不修改")
     */
    public function update(Request $request, string $id): Response
    {
        $planId = $this->decodeId($id);
        $plan = $planId ? Plan::find($planId) : null;
        if (!$plan) {
            return $this->fail('套餐不存在', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:50',
            'price' => 'integer|min:0|max:99999999',
            'valid_days' => 'integer|min:1|max:3650',
            'status' => 'in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if ($request->input('name') !== null && trim((string) $request->input('name')) !== '') {
            $name = trim((string) $request->input('name'));
            if (Plan::where('name', $name)->where('id', '!=', $plan->id)->exists()) {
                return $this->fail('套餐名称已存在', 422);
            }
            $plan->name = $name;
        }
        if ($request->input('price') !== null) {
            $plan->price = (int) $request->input('price');
        }
        if ($request->input('valid_days') !== null) {
            $plan->valid_days = (int) $request->input('valid_days');
        }
        if ($request->input('status') !== null) {
            $plan->status = (int) $request->input('status');
        }
        $plan->save();

        return $this->success($this->encodeIds(['id' => $plan->id, 'name' => $plan->name]), '更新成功');
    }

    /**
     * @Apidoc\Title("删除套餐")
     * @Apidoc\Group("套餐管理")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Url("/admin/plan/{id}")
     * @Apidoc\Desc("删除套餐；已被应用或订单引用的套餐不可删除，建议改为停售")
     */
    public function delete(Request $request, string $id): Response
    {
        $planId = $this->decodeId($id);
        $plan = $planId ? Plan::find($planId) : null;
        if (!$plan) {
            return $this->fail('套餐不存在', 404);
        }
        if (ClientApp::where('plan_id', $plan->id)->exists() || Order::where('plan_id', $plan->id)->exists()) {
            return $this->fail('套餐已被应用或订单引用，不可删除，可改为停售', 422);
        }

        $plan->delete();
        return $this->success([], '删除成功');
    }
}

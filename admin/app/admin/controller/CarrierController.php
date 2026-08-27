<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\Carrier;
use support\Request;
use support\Response;
use Webman\Validation\Validator;

/**
 * @Apidoc\Title("承运商管理")
 */
class CarrierController extends BaseController
{
    /**
     * @Apidoc\Title("承运商列表")
     * @Apidoc\Group("承运商管理")
     * @Apidoc\Url("/admin/carrier")
     * @Apidoc\Desc("分页获取承运商列表，支持关键词搜索和通道/状态筛选")
     * @Apidoc\Param("page", type="int", require=false, desc="页码", default="1")
     * @Apidoc\Param("limit", type="int", require=false, desc="每页条数", default="15")
     * @Apidoc\Param("keyword", type="string", require=false, desc="搜索关键词(code/name)")
     * @Apidoc\Param("channel", type="string", require=false, desc="通道筛选(domestic/international)")
     * @Apidoc\Param("status", type="int", require=false, desc="状态筛选(0禁用1启用)")
     * @Apidoc\Returned("list", type="array", desc="承运商列表")
     * @Apidoc\Returned("total", type="int", desc="总数")
     * @Apidoc\Returned("page", type="int", desc="当前页码")
     * @Apidoc\Returned("limit", type="int", desc="每页条数")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = trim((string) $request->input('keyword', ''));
        $channel = $request->input('channel');
        $status = $request->input('status');

        $query = Carrier::query();
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                  ->orWhere('name', 'like', "%{$keyword}%");
            });
        }
        if ($channel !== null && $channel !== '') {
            $query->where('channel', (string) $channel);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('sort', 'asc')
                      ->orderBy('id', 'asc')
                      ->get()
                      ->map(fn($carrier) => $this->encodeIds($carrier->toArray()));

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("创建承运商")
     * @Apidoc\Group("承运商管理")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/admin/carrier")
     * @Apidoc\Desc("创建新承运商")
     * @Apidoc\Param("code", type="string", require=true, desc="承运商代码")
     * @Apidoc\Param("name", type="string", require=true, desc="承运商名称")
     * @Apidoc\Param("channel", type="string", require=false, desc="通道", default="domestic")
     * @Apidoc\Param("country", type="string", require=false, desc="所属国家/地区")
     * @Apidoc\Param("status", type="int", require=false, desc="状态", default="1")
     * @Apidoc\Param("timeout_ms", type="int", require=false, desc="查询超时(毫秒)", default="5000")
     * @Apidoc\Param("cache_ttl", type="int", require=false, desc="缓存时间(秒)", default="300")
     * @Apidoc\Param("sort", type="int", require=false, desc="排序值", default="0")
     */
    public function store(Request $request): Response
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'channel' => 'in:domestic,international',
            'country' => 'string|max:50',
            'status' => 'in:0,1',
            'timeout_ms' => 'integer|min:100|max:120000',
            'cache_ttl' => 'integer|min:0|max:86400',
            'sort' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $code = strtolower(trim((string) $request->input('code')));
        if (Carrier::where('code', $code)->exists()) {
            return $this->fail('承运商代码已存在', 422);
        }

        $carrier = new Carrier();
        $carrier->id = $this->generateId();
        $carrier->code = $code;
        $carrier->name = $request->input('name');
        $carrier->channel = $request->input('channel', 'domestic');
        $carrier->country = $request->input('country', '');
        $carrier->logo = $request->input('logo', '');
        $carrier->status = (int) $request->input('status', 1);
        $carrier->timeout_ms = (int) $request->input('timeout_ms', 5000);
        $carrier->cache_ttl = (int) $request->input('cache_ttl', 300);
        $carrier->sort = (int) $request->input('sort', 0);
        $carrier->remark = $request->input('remark', '');
        $carrier->save();

        return $this->success($this->encodeIds($carrier->toArray()), '创建成功');
    }

    /**
     * @Apidoc\Title("承运商详情")
     * @Apidoc\Group("承运商管理")
     * @Apidoc\Url("/admin/carrier/{id}")
     * @Apidoc\Desc("获取指定承运商的详细信息")
     * @Apidoc\Param("id", type="string", require=true, desc="承运商hashid")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $carrier = Carrier::find($id);
        if (!$carrier) {
            return $this->fail('承运商不存在', 404);
        }

        return $this->success($this->encodeIds($carrier->toArray()));
    }

    /**
     * @Apidoc\Title("更新承运商")
     * @Apidoc\Group("承运商管理")
     * @Apidoc\Method("PUT")
     * @Apidoc\Url("/admin/carrier/{id}")
     * @Apidoc\Desc("更新指定承运商的信息")
     * @Apidoc\Param("id", type="string", require=true, desc="承运商hashid")
     * @Apidoc\Param("name", type="string", require=false, desc="承运商名称")
     * @Apidoc\Param("channel", type="string", require=false, desc="通道")
     * @Apidoc\Param("status", type="int", require=false, desc="状态")
     * @Apidoc\Param("timeout_ms", type="int", require=false, desc="查询超时(毫秒)")
     * @Apidoc\Param("cache_ttl", type="int", require=false, desc="缓存时间(秒)")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $carrier = Carrier::find($id);
        if (!$carrier) {
            return $this->fail('承运商不存在', 404);
        }

        $validator = Validator::make($request->all(), [
            'channel' => 'in:domestic,international',
            'status' => 'in:0,1',
            'timeout_ms' => 'integer|min:100|max:120000',
            'cache_ttl' => 'integer|min:0|max:86400',
            'sort' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        foreach (['name', 'channel', 'country', 'logo', 'remark'] as $field) {
            if ($request->input($field) !== null) {
                $carrier->$field = $request->input($field);
            }
        }
        if ($request->input('status') !== null) {
            $carrier->status = (int) $request->input('status');
        }
        if ($request->input('timeout_ms') !== null) {
            $carrier->timeout_ms = (int) $request->input('timeout_ms');
        }
        if ($request->input('cache_ttl') !== null) {
            $carrier->cache_ttl = (int) $request->input('cache_ttl');
        }
        if ($request->input('sort') !== null) {
            $carrier->sort = (int) $request->input('sort');
        }
        $carrier->save();

        return $this->success($this->encodeIds($carrier->toArray()), '更新成功');
    }

    /**
     * @Apidoc\Title("删除承运商")
     * @Apidoc\Group("承运商管理")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Url("/admin/carrier/{id}")
     * @Apidoc\Desc("删除指定承运商及其凭证")
     * @Apidoc\Param("id", type="string", require=true, desc="承运商hashid")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $carrier = Carrier::find($id);
        if (!$carrier) {
            return $this->fail('承运商不存在', 404);
        }

        $carrier->credentials()->delete();
        $carrier->delete();

        return $this->success([], '删除成功');
    }
}

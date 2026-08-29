<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\EncryptionService;
use app\model\Carrier;
use app\model\CarrierCredential;
use support\Request;
use support\Response;
use Webman\Validation\Validator;

/**
 * @Apidoc\Title("承运商凭证管理")
 */
class CarrierCredentialController extends BaseController
{
    /**
     * @Apidoc\Title("凭证列表")
     * @Apidoc\Group("承运商凭证管理")
     * @Apidoc\Url("/admin/carrier/credential")
     * @Apidoc\Desc("分页获取凭证列表，按承运商筛选")
     * @Apidoc\Param("page", type="int", require=false, desc="页码", default="1")
     * @Apidoc\Param("limit", type="int", require=false, desc="每页条数", default="15")
     * @Apidoc\Param("carrier_id", type="string", require=false, desc="承运商hashid")
     * @Apidoc\Param("status", type="int", require=false, desc="状态筛选(0禁用1启用)")
     * @Apidoc\Returned("list", type="array", desc="凭证列表")
     * @Apidoc\Returned("total", type="int", desc="总数")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $status = $request->input('status');

        $query = CarrierCredential::query()->with('carrier:id,code,name');
        if ($request->input('carrier_id') !== null && $request->input('carrier_id') !== '') {
            $query->where('carrier_id', $this->decodeId($request->input('carrier_id')));
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'asc')
                      ->get()
                      ->map(function ($credential) {
                          $data = $this->encodeIds($credential->toArray(), ['id', 'carrier_id']);
                          if (isset($data['carrier']['id'])) {
                              $data['carrier']['id'] = $this->encodeId((int) $data['carrier']['id']);
                          }
                          $data['app_secret'] = '****';
                          $data['app_key'] = EncryptionService::maskSecret((string) ($data['app_key'] ?? ''));
                          return $data;
                      });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("创建凭证")
     * @Apidoc\Group("承运商凭证管理")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/admin/carrier/credential")
     * @Apidoc\Desc("创建新凭证，app_key/app_secret 加密存储")
     * @Apidoc\Param("carrier_id", type="string", require=true, desc="承运商hashid")
     * @Apidoc\Param("name", type="string", require=true, desc="凭证名称")
     * @Apidoc\Param("app_key", type="string", require=false, desc="App Key(传输层加密)")
     * @Apidoc\Param("app_secret", type="string", require=false, desc="App Secret(传输层加密)")
     * @Apidoc\Param("extra", type="object", require=false, desc="扩展参数(JSON对象)")
     * @Apidoc\Param("status", type="int", require=false, desc="状态", default="1")
     */
    public function store(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'carrier_id' => 'required|string',
            'name' => 'required|string|max:100',
            'app_key' => 'string|max:200',
            'app_secret' => 'string|max:200',
            'status' => 'in:0,1',
            'weight' => 'nullable|integer|min:1|max:10000',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $carrierId = $this->decodeId($request->input('carrier_id'));
        if (!Carrier::where('id', $carrierId)->exists()) {
            return $this->fail('承运商不存在', 422);
        }

        $credential = new CarrierCredential();
        $credential->id = $this->generateId();
        $credential->carrier_id = $carrierId;
        $credential->name = $request->input('name');
        $credential->app_key = EncryptionService::decryptTransmission($request->input('app_key', ''));
        $credential->app_secret = EncryptionService::decryptTransmission($request->input('app_secret', ''));
        $credential->extra = $request->input('extra');
        $credential->status = (int) $request->input('status', 1);
        $credential->weight = (int) $request->input('weight', 100);
        $credential->save();

        return $this->success($this->encodeIds($credential->toArray(), ['id', 'carrier_id']), '创建成功');
    }

    /**
     * @Apidoc\Title("凭证详情")
     * @Apidoc\Group("承运商凭证管理")
     * @Apidoc\Url("/admin/carrier/credential/{id}")
     * @Apidoc\Desc("获取指定凭证的详细信息（app_key/app_secret 为解密后的明文）")
     * @Apidoc\Param("id", type="string", require=true, desc="凭证hashid")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $credential = CarrierCredential::find($id);
        if (!$credential) {
            return $this->fail('凭证不存在', 404);
        }

        $data = $this->encodeIds($credential->toArray(), ['id', 'carrier_id']);
        $data['app_secret'] = '****';
        $data['app_key'] = EncryptionService::maskSecret((string) ($data['app_key'] ?? ''));
        return $this->success($data);
    }

    /**
     * @Apidoc\Title("更新凭证")
     * @Apidoc\Group("承运商凭证管理")
     * @Apidoc\Method("PUT")
     * @Apidoc\Url("/admin/carrier/credential/{id}")
     * @Apidoc\Desc("更新指定凭证，留空字段不修改")
     * @Apidoc\Param("id", type="string", require=true, desc="凭证hashid")
     * @Apidoc\Param("name", type="string", require=false, desc="凭证名称")
     * @Apidoc\Param("app_key", type="string", require=false, desc="App Key(传输层加密)")
     * @Apidoc\Param("app_secret", type="string", require=false, desc="App Secret(传输层加密)")
     * @Apidoc\Param("extra", type="object", require=false, desc="扩展参数(JSON对象)")
     * @Apidoc\Param("status", type="int", require=false, desc="状态")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $credential = CarrierCredential::find($id);
        if (!$credential) {
            return $this->fail('凭证不存在', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:100',
            'app_key' => 'string|max:200',
            'app_secret' => 'string|max:200',
            'status' => 'in:0,1',
            'weight' => 'nullable|integer|min:1|max:10000',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if ($request->input('name') !== null) {
            $credential->name = $request->input('name');
        }
        if ($request->input('app_key') !== null && $request->input('app_key') !== '') {
            $credential->app_key = EncryptionService::decryptTransmission($request->input('app_key'));
        }
        if ($request->input('app_secret') !== null && $request->input('app_secret') !== '') {
            $credential->app_secret = EncryptionService::decryptTransmission($request->input('app_secret'));
        }
        if ($request->input('extra') !== null) {
            $credential->extra = $request->input('extra');
        }
        if ($request->input('status') !== null) {
            $credential->status = (int) $request->input('status');
        }
        if ($request->input('weight') !== null) {
            $credential->weight = (int) $request->input('weight');
        }
        $credential->save();

        return $this->success($this->encodeIds($credential->toArray(), ['id', 'carrier_id']), '更新成功');
    }

    /**
     * @Apidoc\Title("删除凭证")
     * @Apidoc\Group("承运商凭证管理")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Url("/admin/carrier/credential/{id}")
     * @Apidoc\Desc("删除指定凭证")
     * @Apidoc\Param("id", type="string", require=true, desc="凭证hashid")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $credential = CarrierCredential::find($id);
        if (!$credential) {
            return $this->fail('凭证不存在', 404);
        }

        $credential->delete();

        return $this->success([], '删除成功');
    }
}

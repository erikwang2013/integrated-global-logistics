<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\EncryptionService;
use app\model\CdnProvider;
use support\Request;
use support\Response;
use Webman\Validation\Validator;

/**
 * @Apidoc\Title("CDN服务商管理")
 */
class CdnProviderController extends BaseController
{
    /**
     * @Apidoc\Title("CDN服务商列表")
     * @Apidoc\Group("CDN服务商管理")
     * @Apidoc\Url("/admin/cdn/provider")
     * @Apidoc\Desc("分页获取CDN服务商列表，支持关键词搜索和状态筛选")
     * @Apidoc\Param("page", type="int", require=false, desc="页码", default="1")
     * @Apidoc\Param("limit", type="int", require=false, desc="每页条数", default="15")
     * @Apidoc\Param("keyword", type="string", require=false, desc="搜索关键词(code/name)")
     * @Apidoc\Param("status", type="int", require=false, desc="状态筛选(0禁用1启用)")
     * @Apidoc\Returned("list", type="array", desc="CDN服务商列表")
     * @Apidoc\Returned("total", type="int", desc="总数")
     * @Apidoc\Returned("page", type="int", desc="当前页码")
     * @Apidoc\Returned("limit", type="int", desc="每页条数")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = trim((string) $request->input('keyword', ''));
        $status = $request->input('status');

        $query = CdnProvider::query();
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                  ->orWhere('name', 'like', "%{$keyword}%");
            });
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
                      ->map(fn($provider) => $this->maskCredential($this->encodeIds($provider->toArray())));

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("创建CDN服务商")
     * @Apidoc\Group("CDN服务商管理")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/admin/cdn/provider")
     * @Apidoc\Desc("创建CDN服务商，access_key/access_secret 加密存储")
     * @Apidoc\Param("code", type="string", require=true, desc="服务商代码(cloudflare/cloudfront/aliyun/tencent)")
     * @Apidoc\Param("name", type="string", require=true, desc="服务商显示名称")
     * @Apidoc\Param("access_key", type="string", require=false, desc="凭证Key(传输层加密)")
     * @Apidoc\Param("access_secret", type="string", require=false, desc="凭证Secret(传输层加密)")
     * @Apidoc\Param("extra", type="object", require=false, desc="扩展参数(JSON对象)")
     * @Apidoc\Param("domains", type="array", require=false, desc="域名列表(JSON数组)")
     * @Apidoc\Param("status", type="int", require=false, desc="状态", default="1")
     * @Apidoc\Param("sort", type="int", require=false, desc="排序值", default="0")
     * @Apidoc\Param("remark", type="string", require=false, desc="备注")
     */
    public function store(Request $request): Response
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'access_key' => 'string|max:500',
            'access_secret' => 'string|max:500',
            'extra' => 'array',
            'domains' => 'array',
            'status' => 'in:0,1',
            'sort' => 'integer',
            'remark' => 'string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $code = strtolower(trim((string) $request->input('code')));
        if (!in_array($code, ['cloudflare', 'cloudfront', 'aliyun', 'tencent'], true)) {
            return $this->fail('不支持的服务商代码', 422);
        }
        if (CdnProvider::where('code', $code)->exists()) {
            return $this->fail('服务商代码已存在', 422);
        }

        $provider = new CdnProvider();
        $provider->id = $this->generateId();
        $provider->code = $code;
        $provider->name = $request->input('name');
        $provider->access_key = EncryptionService::decryptTransmission($request->input('access_key', ''));
        $provider->access_secret = EncryptionService::decryptTransmission($request->input('access_secret', ''));
        $provider->extra = $request->input('extra');
        $provider->domains = $request->input('domains', []);
        $provider->status = (int) $request->input('status', 1);
        $provider->sort = (int) $request->input('sort', 0);
        $provider->remark = $request->input('remark', '');
        $provider->save();

        return $this->success($this->maskCredential($this->encodeIds($provider->toArray())), '创建成功');
    }

    /**
     * @Apidoc\Title("CDN服务商详情")
     * @Apidoc\Group("CDN服务商管理")
     * @Apidoc\Url("/admin/cdn/provider/{id}")
     * @Apidoc\Desc("获取指定CDN服务商信息（凭证字段掩码返回）")
     * @Apidoc\Param("id", type="string", require=true, desc="服务商hashid")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $provider = CdnProvider::find($id);
        if (!$provider) {
            return $this->fail('CDN服务商不存在', 404);
        }

        return $this->success($this->maskCredential($this->encodeIds($provider->toArray())));
    }

    /**
     * @Apidoc\Title("更新CDN服务商")
     * @Apidoc\Group("CDN服务商管理")
     * @Apidoc\Method("PUT")
     * @Apidoc\Url("/admin/cdn/provider/{id}")
     * @Apidoc\Desc("更新指定CDN服务商，凭证字段留空不修改")
     * @Apidoc\Param("id", type="string", require=true, desc="服务商hashid")
     * @Apidoc\Param("name", type="string", require=false, desc="服务商显示名称")
     * @Apidoc\Param("access_key", type="string", require=false, desc="凭证Key(传输层加密)")
     * @Apidoc\Param("access_secret", type="string", require=false, desc="凭证Secret(传输层加密)")
     * @Apidoc\Param("extra", type="object", require=false, desc="扩展参数(JSON对象)")
     * @Apidoc\Param("domains", type="array", require=false, desc="域名列表(JSON数组)")
     * @Apidoc\Param("status", type="int", require=false, desc="状态")
     * @Apidoc\Param("sort", type="int", require=false, desc="排序值")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $provider = CdnProvider::find($id);
        if (!$provider) {
            return $this->fail('CDN服务商不存在', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:100',
            'access_key' => 'string|max:500',
            'access_secret' => 'string|max:500',
            'extra' => 'array',
            'domains' => 'array',
            'status' => 'in:0,1',
            'sort' => 'integer',
            'remark' => 'string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        foreach (['name', 'remark'] as $field) {
            if ($request->input($field) !== null) {
                $provider->$field = $request->input($field);
            }
        }
        if ($request->input('access_key') !== null && $request->input('access_key') !== '') {
            $provider->access_key = EncryptionService::decryptTransmission($request->input('access_key'));
        }
        if ($request->input('access_secret') !== null && $request->input('access_secret') !== '') {
            $provider->access_secret = EncryptionService::decryptTransmission($request->input('access_secret'));
        }
        if ($request->input('extra') !== null) {
            $provider->extra = $request->input('extra');
        }
        if ($request->input('domains') !== null) {
            $provider->domains = $request->input('domains');
        }
        if ($request->input('status') !== null) {
            $provider->status = (int) $request->input('status');
        }
        if ($request->input('sort') !== null) {
            $provider->sort = (int) $request->input('sort');
        }
        $provider->save();

        return $this->success($this->maskCredential($this->encodeIds($provider->toArray())), '更新成功');
    }

    /**
     * @Apidoc\Title("删除CDN服务商")
     * @Apidoc\Group("CDN服务商管理")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Url("/admin/cdn/provider/{id}")
     * @Apidoc\Desc("删除指定CDN服务商")
     * @Apidoc\Param("id", type="string", require=true, desc="服务商hashid")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $provider = CdnProvider::find($id);
        if (!$provider) {
            return $this->fail('CDN服务商不存在', 404);
        }

        $provider->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 凭证字段掩码：access_secret 恒为 ****，access_key 仅显示首尾 4 位
     */
    private function maskCredential(array $data): array
    {
        $data['access_secret'] = '****';
        $data['access_key'] = EncryptionService::maskSecret((string) ($data['access_key'] ?? ''));
        return $data;
    }
}

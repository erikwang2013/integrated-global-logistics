<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\SnowflakeService;
use support\Request;
use support\Response;

/**
 * 系统安装向导
 *
 * 首次部署时通过 Web 界面完成:
 *   1. 数据库连接配置
 *   2. 管理员账号设置
 *   3. 执行 database/install.sql
 */
class InstallController
{
    /**
     * 步骤 1: 数据库配置表单
     */
    public function index(Request $request): Response
    {
        if ($resp = $this->installedResponse()) {
            return $resp;
        }
        return response($this->renderStep1());
    }

    /**
     * 步骤 2: 验证数据库 → 管理员设置
     */
    public function step2(Request $request): Response
    {
        if ($resp = $this->installedResponse()) {
            return $resp;
        }
        $db = $request->post();
        $errors = $this->validateDb($db);
        if ($errors) {
            return response($this->renderStep1(implode('；', $errors)));
        }
        $connError = $this->testConnection($db);
        if ($connError) {
            return response($this->renderStep1($connError));
        }
        return response($this->renderStep2($db));
    }

    /**
     * 执行安装
     */
    public function install(Request $request): Response
    {
        if ($resp = $this->installedResponse()) {
            return $resp;
        }
        $data = $request->post();
        $db = $data['db'] ?? [];
        $admin = $data['admin'] ?? [];

        $dbErrors = $this->validateDb($db);
        if ($dbErrors) {
            return response($this->renderStep1(implode('；', $dbErrors)));
        }
        $adminErrors = $this->validateAdmin($admin);
        if ($adminErrors) {
            return response($this->renderStep2($db, implode('；', $adminErrors)));
        }

        try {
            $pdo = $this->getPdo($db);
            $tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'logistics_admin_user'")->fetch();
            if ($tableExists) {
                // 已有管理员则拒绝安装，防止 install.lock 被误删后清空数据
                $count = (int) $pdo->query('SELECT COUNT(*) FROM logistics_admin_user')->fetchColumn();
                if ($count > 0) {
                    return response(
                        '<h1>系统已安装</h1><p>数据库已存在管理员账号，禁止重复安装。如需重装，请先备份数据库并手动清理数据。</p>',
                        403,
                        ['Content-Type' => 'text/html; charset=utf-8']
                    );
                }
                // Docker 初始化已建表但无管理员：跳过 install.sql，只创建管理员
            } else {
                $pdo->exec(file_get_contents(base_path() . '/database/install.sql'));
            }

            // 先写 .env 再建管理员，避免 .env 不可写时留下半安装状态
            $this->writeEnv($db);
            $this->createAdmin($pdo, $admin);
            if (file_put_contents(runtime_path('install.lock'), date('Y-m-d H:i:s')) === false) {
                throw new \RuntimeException('写入 runtime/install.lock 失败，请检查 runtime 目录权限');
            }

            return response($this->renderSuccess($admin));
        } catch (\Throwable $e) {
            return response($this->renderStep2($db, '安装失败: ' . $e->getMessage()));
        }
    }

    /**
     * install.lock 存在时返回 403；注意不能 exit，否则会结束整个 workerman worker
     */
    private function installedResponse(): ?Response
    {
        if (!is_file(runtime_path('install.lock'))) {
            return null;
        }
        return response(
            '<h1>系统已安装</h1><p>如需重新安装，请先备份数据库并手动删除 runtime/install.lock。</p>',
            403,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    private function createAdmin(\PDO $pdo, array $admin): void
    {
        $id = SnowflakeService::generate();
        $realName = (string) ($admin['real_name'] ?? '') ?: '系统管理员';
        $pdo->prepare(
            'INSERT INTO logistics_admin_user (id, username, password, real_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())'
        )->execute([
            $id,
            (string) $admin['username'],
            password_hash((string) $admin['password'], PASSWORD_BCRYPT),
            $realName,
        ]);
        $pdo->prepare('INSERT INTO logistics_admin_user_role (user_id, role_id) VALUES (?, ?)')
            ->execute([$id, 10000000000000001]);
    }

    private function validateDb(array $db): array
    {
        $errors = [];
        foreach (['host' => '数据库主机', 'port' => '端口', 'database' => '数据库名', 'username' => '用户名'] as $k => $label) {
            if (empty($db[$k])) $errors[] = "{$label} 不能为空";
        }
        return $errors;
    }

    private function validateAdmin(array $admin): array
    {
        $errors = [];
        $username = (string) ($admin['username'] ?? '');
        $password = (string) ($admin['password'] ?? '');
        if (strlen($username) < 3 || strlen($username) > 50) $errors[] = '用户名长度须为 3-50 个字符';
        if (strlen($password) < 6 || strlen($password) > 32) $errors[] = '密码长度须为 6-32 个字符';
        if ($password !== (string) ($admin['password_confirm'] ?? '')) $errors[] = '两次密码不一致';
        return $errors;
    }

    private function testConnection(array $db): string
    {
        try { $this->getPdo($db)->query('SELECT 1'); return ''; }
        catch (\Throwable $e) { return '数据库连接失败: ' . $e->getMessage(); }
    }

    private function getPdo(array $db): \PDO
    {
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4";
        return new \PDO($dsn, $db['username'], $db['password'] ?? '', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    private function writeEnv(array $db): void
    {
        $envFile = base_path() . '/.env';
        $env = is_file($envFile) ? (string) file_get_contents($envFile) : '';

        // 仓库中的公开默认密钥视为未配置，安装时生成随机值；已有非默认值不覆盖
        $publicDefaults = [
            'JWT_SECRET_KEY'  => 'open-admin-jwt-secret-change-in-production',
            'ENCRYPTABLE_KEY' => 'open-admin-db-encryption-key-32b',
            'ENCRYPTION_KEY'  => 'open-admin-api-encryption-key32b',
            'INTERNAL_TOKEN'  => 'lg-internal-8f3a2c9e6b1d4f7a',
        ];
        $generated = [
            'JWT_SECRET_KEY'  => bin2hex(random_bytes(32)),
            'ENCRYPTABLE_KEY' => bin2hex(random_bytes(16)),
            'ENCRYPTION_KEY'  => bin2hex(random_bytes(16)),
            'INTERNAL_TOKEN'  => 'lg-internal-' . bin2hex(random_bytes(16)),
        ];
        $effective = [];
        foreach ($generated as $key => $val) {
            $current = $this->envValue($env, $key);
            if ($current === '' || $current === $publicDefaults[$key]) {
                $effective[$key] = $val;
                $env = $this->setEnvValue($env, $key, $val);
            } else {
                $effective[$key] = $current;
            }
        }
        $env = $this->setEnvValue($env, 'APP_DEBUG', 'false');

        foreach ([
            'DB_HOST'     => $db['host'],
            'DB_PORT'     => $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'] ?? '',
        ] as $key => $val) {
            $env = $this->setEnvValue($env, $key, (string) $val);
        }
        if (file_put_contents($envFile, $env) === false) {
            throw new \RuntimeException('写入 .env 失败，请检查项目根目录权限');
        }

        // Docker 的 env_file 优先级高于 .env：把有效密钥同步进 .env.docker，
        // 否则容器重建后会退回公开默认密钥；非 Docker 部署不去改这个文件
        $isDocker = is_file('/.dockerenv') || getenv('DB_HOST') === 'mysql';
        $dockerEnvFile = base_path() . '/.env.docker';
        if ($isDocker && is_file($dockerEnvFile)) {
            $dockerEnv = @file_get_contents($dockerEnvFile);
            if ($dockerEnv !== false) {
                foreach ($effective as $key => $val) {
                    $current = $this->envValue($dockerEnv, $key);
                    if ($current === '' || $current === $publicDefaults[$key]) {
                        $dockerEnv = $this->setEnvValue($dockerEnv, $key, $val);
                    }
                }
                $dockerEnv = $this->setEnvValue($dockerEnv, 'APP_DEBUG', 'false');
                if (@file_put_contents($dockerEnvFile, $dockerEnv) === false) {
                    error_log('安装器: 写入 .env.docker 失败，Docker 下可能仍使用公开默认密钥');
                }
            }
        }
    }

    private function envValue(string $env, string $key): string
    {
        if (!preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $env, $m)) {
            return '';
        }
        $raw = rtrim($m[1], "\r");
        // phpdotenv 单引号是纯字面量，不做转义；双引号仅转义 \ " $
        if (strlen($raw) >= 2 && $raw[0] === "'" && substr($raw, -1) === "'") {
            return substr($raw, 1, -1);
        }
        if (strlen($raw) >= 2 && $raw[0] === '"' && substr($raw, -1) === '"') {
            return preg_replace_callback('/\\\\([\\\\"$])/', static fn($mm) => $mm[1], substr($raw, 1, -1));
        }
        return trim($raw);
    }

    private function setEnvValue(string $env, string $key, string $val): string
    {
        // 用回调替换，避免密码中的 $ 或 \ 被当成反向引用
        $line = $key . '=' . $this->envQuote(str_replace(["\r", "\n"], '', $val));
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        if (preg_match($pattern, $env)) {
            return preg_replace_callback($pattern, static fn() => $line, $env);
        }
        return ($env === '' ? '' : rtrim($env, "\r\n") . "\n") . $line . "\n";
    }

    /**
     * phpdotenv 会截断/误解析含空白、#、引号、$、反斜杠的裸值，改为引号包裹
     */
    private function envQuote(string $val): string
    {
        if (!preg_match('/[\s#\'"\\\\$]/', $val)) {
            return $val;
        }
        // 双引号 + 转义 \ " $：phpdotenv 与 docker compose env_file 都按此规则解析
        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $val) . '"';
    }

    // ============================================================
    // 视图
    // ============================================================

    private function renderStep1(string $error = ''): string
    {
        $host = htmlspecialchars((string) (getenv('DB_HOST') ?: '127.0.0.1'), ENT_QUOTES, 'UTF-8');
        $port = htmlspecialchars((string) (getenv('DB_PORT') ?: '3306'), ENT_QUOTES, 'UTF-8');
        $database = htmlspecialchars((string) (getenv('DB_DATABASE') ?: 'logistics'), ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars((string) (getenv('DB_USERNAME') ?: 'root'), ENT_QUOTES, 'UTF-8');
        return $this->layout('步骤 1/3 · 数据库配置', <<<HTML
            {$this->alert($error, 'error')}
            <form method="post" action="/install/step2">
                <div class="field"><label>数据库主机</label><input name="host" value="{$host}" required></div>
                <div class="field"><label>端口</label><input name="port" value="{$port}" required></div>
                <div class="field"><label>数据库名</label><input name="database" value="{$database}" required></div>
                <div class="field"><label>用户名</label><input name="username" value="{$username}" required></div>
                <div class="field"><label>密码</label><div class="pwd-wrap"><input name="password" type="password"><span class="eye" onclick="togglePwd(this)">👁</span></div></div>
                <div class="actions"><button type="submit">测试连接 → 下一步</button></div>
            </form>
        HTML);
    }

    private function renderStep2(array $db, string $error = ''): string
    {
        $info = htmlspecialchars("{$db['username']}@{$db['host']}:{$db['port']}/{$db['database']}", ENT_QUOTES, 'UTF-8');
        $hidden = '';
        foreach ($db as $k => $v) {
            if (!is_scalar($v)) continue;
            $hidden .= '<input type="hidden" name="db[' . htmlspecialchars($k) . ']" value="' . htmlspecialchars((string) $v) . '">';
        }
        return $this->layout('步骤 2/3 · 管理员账号', <<<HTML
            <div class="info">数据库连接成功: <code>{$info}</code></div>
            {$this->alert($error, 'error')}
            <form method="post" action="/install/install">
                {$hidden}
                <div class="field"><label>管理员用户名</label><input name="admin[username]" placeholder="至少 3 个字符" autocomplete="username" required></div>
                <div class="field"><label>管理员密码</label><div class="pwd-wrap"><input name="admin[password]" type="password" placeholder="至少 6 个字符" autocomplete="new-password" required><span class="eye" onclick="togglePwd(this)">👁</span></div></div>
                <div class="field"><label>确认密码</label><div class="pwd-wrap"><input name="admin[password_confirm]" type="password" placeholder="再次输入密码" autocomplete="new-password" required><span class="eye" onclick="togglePwd(this)">👁</span></div></div>
                <div class="field"><label>姓名</label><input name="admin[real_name]" value="系统管理员"></div>
                <div class="actions"><button type="submit">开始安装</button></div>
            </form>
        HTML);
    }

    private function renderSuccess(array $admin): string
    {
        $username = htmlspecialchars((string) $admin['username'], ENT_QUOTES, 'UTF-8');
        $password = htmlspecialchars((string) $admin['password'], ENT_QUOTES, 'UTF-8');
        return $this->layout('安装完成', <<<HTML
            <div style="text-align:center">
                <h2 style="color:#52c41a;margin-bottom:16px">安装成功</h2>
                <p>管理员账号: <strong>{$username}</strong></p>
                <p>管理员密码: <strong>{$password}</strong></p>
                <hr>
                <a href="/" style="display:inline-block;padding:10px 32px;background:#1890ff;color:#fff;border-radius:6px;text-decoration:none;font-size:15px">进入系统</a>
                <p style="margin-top:12px;font-size:12px;color:#999">请保留 <code>runtime/install.lock</code>；如需重装请先备份数据库并手动删除该文件。</p>
                <p style="font-size:12px;color:#999">安装完成后请重建应用容器（Docker: <code>docker compose up -d --force-recreate app</code>），使新生成的密钥生效。</p>
                <p style="font-size:12px;color:#999">非 Docker 部署请重启 worker：<code>php start.php restart -d</code>，否则旧进程仍使用安装前的空密钥/旧数据库配置。</p>
                <p style="font-size:12px;color:#999">请将 .env（Docker 为 .env.docker）中的 <code>INTERNAL_TOKEN</code> 同步到查询网关的 <code>internal_token</code>。</p>
            </div>
        HTML);
    }

    private function alert(string $msg, string $type): string
    {
        if (empty($msg)) return '';
        $msg = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
        $color = $type === 'error' ? '#cf1322' : '#0050b3';
        $bg   = $type === 'error' ? '#fff2f0' : '#e6f7ff';
        $border = $type === 'error' ? '#ffccc7' : '#91d5ff';
        return "<div style='background:{$bg};border:1px solid {$border};color:{$color};padding:12px;border-radius:6px;margin-bottom:16px;font-size:13px'>{$msg}</div>";
    }

    private function layout(string $title, string $body): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
            <title>{$title} — 开放管理后台</title>
            <link rel="icon" type="image/svg+xml" href="/favicon.svg">
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f0f2f5;color:#333;min-height:100vh;display:flex;align-items:center;justify-content:center}
                .box{background:#fff;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.08);width:460px;max-width:95vw;padding:36px 40px}
                .pet{display:block;width:104px;height:104px;margin:0 auto 6px}
                h1{font-size:22px;text-align:center;margin-bottom:6px;color:#1a1a2e}
                .sub{text-align:center;color:#999;font-size:13px;margin-bottom:20px}
                .field{margin-bottom:16px}
                .field label{display:block;font-size:13px;font-weight:500;margin-bottom:4px;color:#555}
                .field input{width:100%;padding:10px 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;transition:border-color .2s}
                .field input:focus{outline:none;border-color:#1890ff;box-shadow:0 0 0 2px rgba(24,144,255,.1)}
                .actions{margin-top:24px;text-align:center}
                .actions button{padding:10px 36px;background:#1890ff;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer}
                .actions button:hover{background:#40a9ff}
                .info{background:#e6f7ff;border:1px solid #91d5ff;color:#0050b3;padding:12px;border-radius:6px;margin-bottom:16px;font-size:13px;word-break:break-all}
                hr{border:none;border-top:1px solid #f0f0f0;margin:16px 0}
                .pwd-wrap{position:relative}.pwd-wrap input{padding-right:40px}
                .eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:18px;user-select:none;opacity:.5;transition:opacity .2s}
                .eye:hover{opacity:1}.eye.show{opacity:1}
            </style>
        </head>
        <body>
            <div class="box">
                <img class="pet" src="/mascot.svg" alt="E-Cat 项目宠物" width="104" height="104">
                <h1>开放管理后台</h1>
                <div class="sub">安装向导 · {$title}</div>
                {$body}
            </div>
            <script>function togglePwd(el){var inp=el.parentNode.querySelector('input');if(inp.type==='password'){inp.type='text';el.classList.add('show')}else{inp.type='password';el.classList.remove('show')}}</script>
        </body>
        </html>
        HTML;
    }
}

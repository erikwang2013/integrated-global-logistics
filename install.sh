#!/usr/bin/env bash
# ============================================================
# 物流聚合平台 — Docker Compose 一键安装脚本
# 用法：bash install.sh（或 ./install.sh）
# 环境变量：NGINX_PORT（默认 80）覆盖对外端口
# ============================================================
set -euo pipefail

# 检查 docker
if ! command -v docker >/dev/null 2>&1; then
    echo "错误：未检测到 Docker。请先安装 Docker 后再运行：" >&2
    echo "  https://docs.docker.com/engine/install/" >&2
    exit 1
fi

# 检查 docker compose 插件
if ! docker compose version >/dev/null 2>&1; then
    echo "错误：未检测到 Docker Compose 插件。请先安装：" >&2
    echo "  https://docs.docker.com/compose/install/" >&2
    exit 1
fi

NGINX_PORT="${NGINX_PORT:-80}"

# 进入 admin 目录（脚本位于仓库根目录）
cd "$(dirname "$0")/admin"

# .env.docker 已提交；若缺失则尝试从示例复制
if [ ! -f .env.docker ]; then
    if [ -f .env.docker.example ]; then
        echo "未找到 .env.docker，从 .env.docker.example 复制..."
        cp .env.docker.example .env.docker
    else
        echo "错误：admin/.env.docker 不存在，请参照 admin/.env.docker.example 手动创建" >&2
        exit 1
    fi
fi

echo "==> 启动 Docker Compose 服务（Nginx / PHP / MySQL / Redis / Elasticsearch）..."
docker compose up -d

echo "==> 轮询健康检查：http://localhost:${NGINX_PORT}/ （最多 120 秒）"
for i in $(seq 1 40); do
    if curl -fsS "http://localhost:${NGINX_PORT}/" >/dev/null 2>&1; then
        echo ""
        echo "==> 服务已就绪！"
        echo ""
        echo "访问 http://localhost:${NGINX_PORT}/install 完成安装向导："
        echo "  - 数据库初始化"
        echo "  - 管理员账号创建"
        echo ""
        echo "提示：如需修改对外端口，设置环境变量 NGINX_PORT 后重新运行："
        echo "  NGINX_PORT=8080 bash install.sh"
        exit 0
    fi
    echo "    等待中...（${i}/40，每 3 秒一次）"
    sleep 3
done

echo "错误：等待 120 秒后服务仍未就绪，请排查：" >&2
echo "  docker compose ps            # 查看各服务状态" >&2
echo "  docker compose logs app      # 查看应用日志" >&2
exit 1

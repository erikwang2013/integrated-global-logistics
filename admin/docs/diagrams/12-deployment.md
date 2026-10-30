# 部署拓扑

```mermaid
flowchart TB
    subgraph dns["DNS/CDN"]
        domain["erik.xyz"]
    end

    subgraph web["Web服务器"]
        nginx["Nginx :443 HTTPS<br/>:80→443 redirect<br/>gzip on"]
        static["静态文件<br/>public/"]
    end

    subgraph app["应用服务器(可横向扩展)"]
        wm1["webman worker 1 :8787"]
        wm2["webman worker 2 :8787"]
        wm3["webman worker N :8787"]
        grpc["InternalGrpcWorker<br/>默认 127.0.0.1:8792<br/>默认 8 workers"]
    end

    subgraph data["数据层"]
        mysql[("MySQL 8.0<br/>主从复制<br/>logistics_前缀")]
        es[("MySQL 8.0<br/>LIKE 检索<br/>logistics_ 前缀")]
        redis[("Redis 7.x<br/>哨兵模式<br/>logistics:cache:* / poster:captcha:*")]
    end

    subgraph monitor["监控"]
        grafana["Grafana+Prometheus"]
    end

    domain --> nginx
    nginx --> static
    nginx --> wm1
    nginx --> wm2
    nginx --> wm3
    wm1 & wm2 & wm3 --> mysql
    wm1 & wm2 & wm3 --> es
    wm1 & wm2 & wm3 --> redis
    wm1 & wm2 & wm3 --> grafana

    style nginx fill:#722ED1,color:#fff
    style wm1 fill:#1677FF,color:#fff
    style wm2 fill:#1677FF,color:#fff
    style wm3 fill:#1677FF,color:#fff
    style mysql fill:#1890FF,color:#fff
    style es fill:#1890FF,color:#fff
    style redis fill:#1890FF,color:#fff
```

> Docker 部署下仅 Nginx 发布宿主 80/443；`app`(8787)、MySQL、Redis 只在容器网络可达。外部查询网关经内部 gRPC（默认 `127.0.0.1:8792`，`INTERNAL_GRPC_LISTEN` 可覆盖）调用 PHP，双方 `INTERNAL_TOKEN` 必须一致。

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](12-deployment.md) | [English](12-deployment.en.md)

# Deployment Topology

```mermaid
flowchart TB
    subgraph dns["DNS/CDN"]
        domain["erik.xyz"]
    end

    subgraph web["Web Server"]
        nginx["Nginx :443 HTTPS<br/>:80→443 redirect<br/>gzip on"]
        static["Static Files<br/>public/"]
    end

    subgraph app["Application Server (Horizontally Scalable)"]
        wm1["webman worker 1 :8787"]
        wm2["webman worker 2 :8787"]
        wm3["webman worker N :8787"]
        grpc["Internal gRPC<br/>127.0.0.1:8792<br/>INTERNAL_TOKEN auth"]
    end

    subgraph data["Data Layer"]
        mysql[("MySQL 8.0<br/>Master-Slave Replication<br/>logistics_ Prefix<br/>no host port published")]
        es[("MySQL 8.0<br/>LIKE Search<br/>logistics_ Prefix")]
        redis[("Redis 7.x<br/>Sentinel Mode<br/>logistics:cache:* (gateway)<br/>poster:captcha:* (captcha)<br/>no host port published")]
    end

    subgraph monitor["Monitoring"]
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

> In Docker Compose only Nginx publishes host ports 80/443; `app` (8787), MySQL (3306) and Redis (6379) stay on the container network. The internal gRPC listener is loopback-only and is not exposed. A stale `app` container keeps old secrets, so recreate it after installation: `docker compose up -d --force-recreate app`.

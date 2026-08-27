> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](12-deployment.md) | [English](12-deployment.en.md) | [한국어](12-deployment.ko.md) | [Русский](12-deployment.ru.md) | [Deutsch](12-deployment.de.md) | [Français](12-deployment.fr.md) | [Español](12-deployment.es.md) | [Português](12-deployment.pt.md) | [हिन्दी](12-deployment.hi.md) | [العربية](12-deployment.ar.md) | [বাংলা](12-deployment.bn.md) | [Bahasa Indonesia](12-deployment.id.md) | [日本語](12-deployment.ja.md)

# デプロイメントトポロジー

```mermaid
flowchart TB
    subgraph dns["DNS/CDN"]
        domain["erik.xyz"]
    end

    subgraph web["Webサーバー"]
        nginx["Nginx :443 HTTPS<br/>:80→443リダイレクト<br/>gzip on"]
        static["静的ファイル<br/>Flutter Web build/"]
    end

    subgraph app["アプリケーションサーバー(水平スケール可能)"]
        wm1["webman worker 1 :8787"]
        wm2["webman worker 2 :8787"]
        wm3["webman worker N :8787"]
    end

    subgraph data["データ層"]
        mysql[("MySQL 8.0<br/>マスタースレーブレプリケーション<br/>logistics_プレフィックス")]
        es[("Elasticsearch 8.x<br/>3ノードクラスター<br/>logistics_プレフィックス")]
        redis[("Redis 7.x<br/>センチネルモード<br/>poster:captcha:*")]
    end

    subgraph monitor["監視"]
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

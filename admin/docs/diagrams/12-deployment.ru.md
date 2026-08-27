> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](12-deployment.md) | [English](12-deployment.en.md) | [한국어](12-deployment.ko.md) | [Русский](12-deployment.ru.md) | [Deutsch](12-deployment.de.md) | [Français](12-deployment.fr.md) | [Español](12-deployment.es.md) | [Português](12-deployment.pt.md) | [हिन्दी](12-deployment.hi.md) | [العربية](12-deployment.ar.md) | [বাংলা](12-deployment.bn.md) | [Bahasa Indonesia](12-deployment.id.md) | [日本語](12-deployment.ja.md)

# Топология развёртывания

```mermaid
flowchart TB
    subgraph dns["DNS/CDN"]
        domain["erik.xyz"]
    end

    subgraph web["Веб-сервер"]
        nginx["Nginx :443 HTTPS<br/>:80→443 redirect<br/>gzip on"]
        static["статические файлы<br/>Flutter Web build/"]
    end

    subgraph app["Сервер приложений (горизонтальное масштабирование)"]
        wm1["webman worker 1 :8787"]
        wm2["webman worker 2 :8787"]
        wm3["webman worker N :8787"]
    end

    subgraph data["Слой данных"]
        mysql[("MySQL 8.0<br/>мастер-реплика<br/>префикс logistics_")]
        es[("Elasticsearch 8.x<br/>кластер из 3 узлов<br/>префикс logistics_")]
        redis[("Redis 7.x<br/>режим Sentinel<br/>poster:captcha:*")]
    end

    subgraph monitor["Мониторинг"]
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

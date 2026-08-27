> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](05-rbac-model.md) | [English](05-rbac-model.en.md) | [한국어](05-rbac-model.ko.md) | [Русский](05-rbac-model.ru.md) | [Deutsch](05-rbac-model.de.md) | [Français](05-rbac-model.fr.md) | [Español](05-rbac-model.es.md) | [Português](05-rbac-model.pt.md) | [हिन्दी](05-rbac-model.hi.md) | [العربية](05-rbac-model.ar.md) | [বাংলা](05-rbac-model.bn.md) | [Bahasa Indonesia](05-rbac-model.id.md) | [日本語](05-rbac-model.ja.md)

# Модель разрешений RBAC

## Связь пользователь-роль-разрешение

```mermaid
flowchart LR
    subgraph users["Пользователи"]
        u1["admin(суперадминистратор)"]
        u2["editor(редактор)"]
        u3["viewer(только чтение)"]
    end

    subgraph roles["Роли"]
        r1["super_admin<br/>идентификатор прав: *"]
        r2["editor<br/>идентификатор прав: get.* post.*"]
        r3["viewer<br/>идентификатор прав: get.*"]
    end

    subgraph permissions["Разрешения (дерево)"]
        p1["dashboard(меню)"]
        p2["user(меню)"]
        p3["get.admin/user(API)"]
        p4["post.admin/user(API)"]
        p5["delete.admin/user(API)"]
        p6["export.excel(кнопка)"]
    end

    u1 --> r1
    u2 --> r2
    u3 --> r3
    r1 --> p1 & p2 & p3 & p4 & p5 & p6
    r2 --> p1 & p2 & p3 & p4
    r3 --> p1 & p3
    p2 --> p3 & p4 & p5
    p1 --> p6

    style u1 fill:#1677FF,color:#fff
    style r1 fill:#FA8C16,color:#fff
    style p1 fill:#52C41A,color:#fff
```

## Процесс определения прав

```mermaid
flowchart TD
    start["Запрос поступил"] --> extract["извлечение Token→adminId"]
    extract --> findRoles["поиск ролей пользователя"]
    findRoles --> collectSlug["сбор всех permission.slug"]
    collectSlug --> buildKey["построение method.path"]
    buildKey --> check{"slug==* или<br/>slug совпадает?"}
    check -->|"да"| allow["200 пропуск"]
    check -->|"нет"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## Типы разрешений

```mermaid
flowchart LR
    t1["type=1 меню<br/>управление отображением боковой панели"]
    t2["type=2 кнопка<br/>управление кнопками операций"]
    t3["type=3 API<br/>управление доступом к интерфейсам"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```

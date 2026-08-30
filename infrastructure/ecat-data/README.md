# e-cat-data

Unified data access traits for the e-cat ecosystem.

## Traits

| Trait | Purpose | Implementations |
|-------|---------|----------------|
| `RdbmsClient` | SQL databases | SQLx, ClickHouse, QuestDB |
| `Cache` | Key-value cache | Redis, Memcached |
| `GraphClient` | Graph databases | Neo4j, ArangoDB, NebulaGraph |
| `SearchClient` | Search engines | Elasticsearch, OpenSearch |
| `TsdbClient` | Time-series DBs | InfluxDB, IoTDB |

## Installation

```bash
cargo add ecat-data
```

## Usage

```rust
use ecat_data::{RdbmsClient, Cache, GraphClient, SearchClient, TsdbClient, StorageClient, DocumentClient};

// Concrete backends implement these traits, e.g.:
// ecat-data-redis / ecat-data-memcached  -> Cache
// ecat-data-sqlx / ecat-data-clickhouse  -> RdbmsClient
```

## Features

- Unified `RdbmsClient` / `Cache` / `GraphClient` / `SearchClient` / `TsdbClient` / `StorageClient` / `DocumentClient` traits
- One shared `Error` type across all backends
- Concrete backends implement the traits, so services can swap storage without code changes

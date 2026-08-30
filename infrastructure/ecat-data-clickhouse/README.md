# ecat-data-clickhouse

ClickHouse analytical database client for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-clickhouse
```

## Features

- Implements the `RdbmsClient` trait from `ecat-data`
- `new(base_url, database)` or `from_config` with auth + TLS
- `execute` / `query` against ClickHouse

## Usage

```rust
use ecat_data_clickhouse::{ClickhouseClient, ClickhouseConfig};

let client = ClickhouseClient::from_config(ClickhouseConfig {
    base_url: "http://localhost:8123".into(),
    database: "default".into(),
    username: None,
    password: None,
    tls: None,
})?;
client.execute("CREATE TABLE IF NOT EXISTS events (id UInt64) ENGINE = MergeTree()").await?;
```

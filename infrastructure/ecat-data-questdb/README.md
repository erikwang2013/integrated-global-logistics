# ecat-data-questdb

QuestDB time-series database client for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-questdb
```

## Features

- Implements the `RdbmsClient` trait from `ecat-data`
- `new(base_url)` or `from_config` with auth + TLS
- `execute` / `query` against QuestDB

## Usage

```rust
use ecat_data_questdb::{QuestdbClient, QuestdbConfig};

let client = QuestdbClient::from_config(QuestdbConfig {
    base_url: "http://localhost:9000".into(),
    username: None,
    password: None,
    tls: None,
})?;
client.execute("INSERT INTO sensors VALUES ('cpu', 23.5)").await?;
```

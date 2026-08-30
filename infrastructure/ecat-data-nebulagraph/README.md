# ecat-data-nebulagraph

NebulaGraph graph database client for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-nebulagraph
```

## Features

- Implements the `GraphClient` trait from `ecat-data`
- `execute(query, params)` with nGQL
- `new(base_url, space)` or `from_config` with auth + TLS

## Usage

```rust
use ecat_data_nebulagraph::{NebulaGraphClient, NebulaGraphConfig};

let client = NebulaGraphClient::from_config(NebulaGraphConfig {
    base_url: "http://localhost:9669".into(),
    space: "demo".into(),
    username: None,
    password: None,
    tls: None,
})?;
client.execute("MATCH (v:user) RETURN v LIMIT 10", &serde_json::json!({})).await?;
```

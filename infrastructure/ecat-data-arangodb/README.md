# ecat-data-arangodb

ArangoDB graph database client for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-arangodb
```

## Features

- Implements the `GraphClient` trait from `ecat-data`
- `execute(query, params)` with AQL
- `from_config` with auth + TLS

## Usage

```rust
use ecat_data_arangodb::{ArangoClient, ArangoConfig};

let client = ArangoClient::from_config(ArangoConfig {
    base_url: "http://localhost:8529".into(),
    db: "app".into(),
    username: "root".into(),
    password: "secret".into(),
    tls: None,
})?;
client.execute("FOR u IN users RETURN u", &serde_json::json!({})).await?;
```

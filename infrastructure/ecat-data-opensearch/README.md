# ecat-data-opensearch

OpenSearch search engine client for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-opensearch
```

## Features

- Implements the `SearchClient` trait from `ecat-data`
- `index` / `search` / `delete` / `bulk_index` operations
- `from_config` with auth + TLS

## Usage

```rust
use ecat_data_opensearch::{OpenSearchClient, OpenSearchConfig};

let client = OpenSearchClient::from_config(OpenSearchConfig {
    base_url: "http://localhost:9200".into(),
    username: None,
    password: None,
    tls: None,
})?;
client.index("users", "1", &serde_json::json!({"name": "erik"})).await?;
```

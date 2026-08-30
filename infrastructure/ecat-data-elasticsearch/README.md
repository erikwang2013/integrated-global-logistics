# ecat-data-elasticsearch

Elasticsearch search engine client for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-elasticsearch
```

## Features

- Implements the `SearchClient` trait from `ecat-data`
- `index` / `search` / `delete` / `bulk_index` operations
- `from_config` with auth + TLS

## Usage

```rust
use ecat_data_elasticsearch::{ElasticsearchClient, ElasticsearchConfig};

let client = ElasticsearchClient::from_config(ElasticsearchConfig {
    base_url: "http://localhost:9200".into(),
    username: None,
    password: None,
    tls: None,
})?;
client.index("users", "1", &serde_json::json!({"name": "erik"})).await?;
```

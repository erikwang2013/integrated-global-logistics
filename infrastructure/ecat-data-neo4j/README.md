# ecat-data-neo4j

Neo4j graph database client for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-neo4j
```

## Features

- Implements the `GraphClient` trait from `ecat-data`
- `execute(query, params)` with parameterized Cypher
- `from_config` with auth + TLS

## Usage

```rust
use ecat_data_neo4j::{Neo4jClient, Neo4jConfig};

let client = Neo4jClient::from_config(Neo4jConfig {
    base_url: "http://localhost:7474".into(),
    username: "neo4j".into(),
    password: "secret".into(),
    tls: None,
})?;
client.execute("CREATE (u:User {name: $name})", &serde_json::json!({"name": "erik"})).await?;
```

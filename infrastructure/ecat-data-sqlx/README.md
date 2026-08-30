# ecat-data-sqlx

SQLx multi-database client (PostgreSQL, MySQL, SQLite) for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-sqlx
```

## Features

- Implements the `RdbmsClient` trait from `ecat-data`
- Multi-database: PostgreSQL / MySQL / SQLite via SQLx `AnyPool`
- `execute` / `query` with parameterized variants and transactions

## Usage

```rust
use ecat_data_sqlx::SqlxClient;

let client = SqlxClient::from_pool(pool); // pool: sqlx::AnyPool
let rows = client.query("SELECT * FROM users").await?;
```

# ecat-cli

Command-line interface for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-cli
```

## Features

- Validate crate names with `validate_crate_name`
- Scaffold service crates: `generate_cargo_toml`, `generate_main_rs`, `generate_proto_file`

## Usage

```rust
use ecat_cli::generate_cargo_toml;

let toml = generate_cargo_toml("my-service");
```

# ecat-openapi

OpenAPI specification generation for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-openapi
```

## Features

- `OpenApiSpec` builder: info, paths, schemas, operations, parameters, responses
- `add_route` / `add_schema` / `build` fluent API
- `schema_ref` / `string_schema` helper constructors

## Usage

```rust
use ecat_openapi::OpenApiSpec;

let spec = OpenApiSpec::new("My API", "1.0.0")
    .add_route(/* method, path, operation */)
    .build();
```

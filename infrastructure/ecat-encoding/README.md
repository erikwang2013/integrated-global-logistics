# ecat-encoding

Content encoding/decoding (JSON, Protobuf) for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-encoding
```

## Features

- `Codec` trait: `encode` / `decode` / `content_type`
- `codec_for(Encoding)` and `codec_from_content_type` factory helpers
- JSON and Protobuf codecs

## Usage

```rust
use ecat_encoding::{codec_for, Encoding};

let codec = codec_for(Encoding::Json);
let bytes = codec.encode(&payload)?;
let payload = codec.decode::<MyType>(&bytes)?;
```

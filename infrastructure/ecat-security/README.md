# e-cat-security

WAF/security scanning middleware for e-cat services.

Detects SQL injection, XSS, and other attack patterns via the
`security-rust` crate. Blocks requests with High/Critical severity
findings.

## Usage

```rust
use ecat_security::SecurityLayer;

let layer = SecurityLayer::new();
```

## Installation

```bash
cargo add ecat-security
```

## Features

- WAF-style security scanning via `security-rust` (SQL injection, XSS, ...)
- `SecurityScanner::scan` / `scan_parts` / `scan_body` with `Severity` + `AttackCategory` results
- Requests with High/Critical severity findings are blocked
- `SecurityError::to_http_status` maps failures to HTTP status codes

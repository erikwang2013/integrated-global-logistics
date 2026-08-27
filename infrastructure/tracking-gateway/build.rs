// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
fn main() -> Result<(), Box<dyn std::error::Error>> {
    tonic_build::compile_protos("proto/internal.proto")?;
    Ok(())
}

// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 极简 JSON 解析器（零第三方依赖，仅需信封 + data 通用取值）
use std::collections::HashMap;

#[derive(Debug, Clone, PartialEq)]
pub enum Value {
    Null,
    Bool(bool),
    Number(f64),
    String(String),
    Array(Vec<Value>),
    Object(HashMap<String, Value>),
}

impl Value {
    pub fn get(&self, key: &str) -> Option<&Value> {
        match self {
            Value::Object(m) => m.get(key),
            _ => None,
        }
    }
    pub fn as_str(&self) -> Option<&str> {
        match self {
            Value::String(s) => Some(s),
            _ => None,
        }
    }
    pub fn as_f64(&self) -> Option<f64> {
        match self {
            Value::Number(n) => Some(*n),
            _ => None,
        }
    }
    pub fn as_array(&self) -> Option<&Vec<Value>> {
        match self {
            Value::Array(a) => Some(a),
            _ => None,
        }
    }
}

pub fn parse(input: &str) -> Result<Value, String> {
    let mut p = Parser { s: input.trim(), i: 0 };
    let v = p.value()?;
    p.skip_ws();
    if p.i != p.s.len() {
        return Err(format!("trailing data at {}", p.i));
    }
    Ok(v)
}

struct Parser<'a> {
    s: &'a str,
    i: usize,
}

impl<'a> Parser<'a> {
    fn peek(&self) -> Option<u8> {
        self.s.as_bytes().get(self.i).copied()
    }
    fn skip_ws(&mut self) {
        while self.peek().map_or(false, |b| b.is_ascii_whitespace()) {
            self.i += 1;
        }
    }
    fn expect(&mut self, lit: &str) -> Result<(), String> {
        if self.s[self.i..].starts_with(lit) {
            self.i += lit.len();
            Ok(())
        } else {
            Err(format!("expected {:?} at {}", lit, self.i))
        }
    }
    fn value(&mut self) -> Result<Value, String> {
        self.skip_ws();
        match self.peek() {
            Some(b'n') => {
                self.expect("null")?;
                Ok(Value::Null)
            }
            Some(b't') => {
                self.expect("true")?;
                Ok(Value::Bool(true))
            }
            Some(b'f') => {
                self.expect("false")?;
                Ok(Value::Bool(false))
            }
            Some(b'"') => self.string().map(Value::String),
            Some(b'[') => self.array(),
            Some(b'{') => self.object(),
            Some(b'-') | Some(b'0'..=b'9') => self.number(),
            _ => Err(format!("unexpected char at {}", self.i)),
        }
    }
    fn string(&mut self) -> Result<String, String> {
        self.expect("\"")?;
        let start = self.i;
        while let Some(b) = self.peek() {
            if b == b'\\' {
                self.i += 2;
                continue;
            }
            if b == b'"' {
                let s = &self.s[start..self.i];
                self.i += 1;
                return Ok(if s.contains('\\') { unescape(s) } else { s.to_string() });
            }
            self.i += 1;
        }
        Err("unterminated string".into())
    }
    fn number(&mut self) -> Result<Value, String> {
        let start = self.i;
        while self.peek().map_or(false, |b| {
            b.is_ascii_digit() || b == b'-' || b == b'.' || b == b'e' || b == b'E' || b == b'+'
        }) {
            self.i += 1;
        }
        self.s[start..self.i]
            .parse::<f64>()
            .map(Value::Number)
            .map_err(|_| format!("bad number at {}", start))
    }
    fn array(&mut self) -> Result<Value, String> {
        self.expect("[")?;
        let mut out = Vec::new();
        self.skip_ws();
        if self.peek() == Some(b']') {
            self.i += 1;
            return Ok(Value::Array(out));
        }
        loop {
            out.push(self.value()?);
            self.skip_ws();
            match self.peek() {
                Some(b',') => self.i += 1,
                Some(b']') => {
                    self.i += 1;
                    return Ok(Value::Array(out));
                }
                _ => return Err(format!("expected , or ] at {}", self.i)),
            }
        }
    }
    fn object(&mut self) -> Result<Value, String> {
        self.expect("{")?;
        let mut m = HashMap::new();
        self.skip_ws();
        if self.peek() == Some(b'}') {
            self.i += 1;
            return Ok(Value::Object(m));
        }
        loop {
            let k = self.string()?;
            self.skip_ws();
            self.expect(":")?;
            let v = self.value()?;
            m.insert(k, v);
            self.skip_ws();
            match self.peek() {
                Some(b',') => self.i += 1,
                Some(b'}') => {
                    self.i += 1;
                    return Ok(Value::Object(m));
                }
                _ => return Err(format!("expected , or }} at {}", self.i)),
            }
        }
    }
}

fn unescape(s: &str) -> String {
    let mut out = String::with_capacity(s.len());
    let mut chars = s.chars();
    while let Some(c) = chars.next() {
        if c != '\\' {
            out.push(c);
            continue;
        }
        match chars.next() {
            Some('n') => out.push('\n'),
            Some('t') => out.push('\t'),
            Some('r') => out.push('\r'),
            Some('"') => out.push('"'),
            Some('\\') => out.push('\\'),
            Some('/') => out.push('/'),
            Some('u') => {
                let hex: String = chars.by_ref().take(4).collect();
                if let Ok(n) = u32::from_str_radix(&hex, 16) {
                    if let Some(ch) = char::from_u32(n) {
                        out.push(ch);
                    }
                }
            }
            _ => out.push('\\'),
        }
    }
    out
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn parses_escaped_quotes() {
        let v = parse(r#"{"a":"x\"y"}"#).unwrap();
        assert_eq!(v.get("a").and_then(Value::as_str), Some("x\"y"));
    }

    #[test]
    fn unescapes_control_chars() {
        let v = parse(r#"{"a":"line1\nline2\t\"q\""}"#).unwrap();
        assert_eq!(v.get("a").and_then(Value::as_str), Some("line1\nline2\t\"q\""));
    }
}

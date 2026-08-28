// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 对外公共端点：GET /v1/openapi.json —— OpenAPI 3.0 文档（不要求 API-Key）。
use axum::{Json, response::IntoResponse};
use ecat_openapi::{
    Components, MediaType, OpenApiInfo, OpenApiSpec, Operation, Parameter, PathItem, RequestBody,
    Response, Schema, array_schema, integer_schema, schema_ref, string_schema,
};
use std::collections::HashMap;

pub async fn openapi_json() -> impl IntoResponse {
    Json(spec())
}

pub fn spec() -> OpenApiSpec {
    OpenApiSpec {
        openapi: "3.0.3".into(),
        info: OpenApiInfo {
            title: "Tracking Gateway API".into(),
            version: "1.0.0".into(),
        },
        paths: paths(),
        components: Some(Components {
            schemas: Some(schemas()),
        }),
    }
}

fn paths() -> HashMap<String, PathItem> {
    let mut m = HashMap::new();
    m.insert(
        "/v1/tracking/query".into(),
        path_item(
            None,
            Some(op(
                "Query tracking by tracking number (requires X-API-Key header)",
                &["tracking"],
                vec![],
                Some(schema_ref("QueryRequest")),
                Some(schema_ref("TrackingOk")),
                &[
                    ("400", "tracking_no is required"),
                    ("401", "invalid or missing api key"),
                    ("429", "rate limit exceeded, retry later"),
                    ("502", "upstream worker failure"),
                    ("503", "carrier temporarily unavailable"),
                ],
            )),
        ),
    );
    m.insert(
        "/v1/tracking/{query_no}".into(),
        path_item(
            Some(op(
                "Get a previous tracking result by query_no (requires X-API-Key header)",
                &["tracking"],
                vec![Parameter {
                    name: "query_no".into(),
                    location: "path".into(),
                    required: true,
                    schema: string_schema(),
                }],
                None,
                Some(schema_ref("TrackingOk")),
                &[
                    ("401", "invalid or missing api key"),
                    ("404", "query not found"),
                ],
            )),
            None,
        ),
    );
    m.insert(
        "/v1/carriers".into(),
        path_item(
            Some(op(
                "List supported carriers (requires X-API-Key header)",
                &["carriers"],
                vec![],
                None,
                Some(schema_ref("CarriersOk")),
                &[
                    ("401", "invalid or missing api key"),
                    ("502", "upstream worker failure"),
                ],
            )),
            None,
        ),
    );
    m.insert(
        "/v1/subscriptions".into(),
        path_item(
            None,
            Some(op(
                "Register a callback subscription for tracking updates (requires X-API-Key header)",
                &["subscriptions"],
                vec![],
                Some(schema_ref("SubscribeRequest")),
                Some(schema_ref("SubscribeOk")),
                &[
                    ("400", "invalid carrier_code or callback_url"),
                    ("401", "invalid or missing api key"),
                    ("502", "upstream worker failure"),
                    ("503", "carrier temporarily unavailable"),
                ],
            )),
        ),
    );
    m.insert(
        "/v1/health".into(),
        path_item(Some(op("Health check (public)", &["ops"], vec![], None, None, &[])), None),
    );
    m
}

fn path_item(get: Option<Operation>, post: Option<Operation>) -> PathItem {
    PathItem {
        get,
        post,
        put: None,
        delete: None,
        patch: None,
        head: None,
        options: None,
    }
}

fn op(
    summary: &str,
    tags: &[&str],
    parameters: Vec<Parameter>,
    request_body: Option<Schema>,
    ok: Option<Schema>,
    errors: &[(&str, &str)],
) -> Operation {
    let mut responses = HashMap::new();
    match ok {
        Some(schema) => {
            responses.insert("200".into(), json_response("Successful response", schema));
        }
        None => {
            responses.insert(
                "200".into(),
                Response {
                    description: "Successful response".into(),
                    content: None,
                },
            );
        }
    }
    for (status, description) in errors {
        responses.insert(
            (*status).to_string(),
            json_response(description, schema_ref("ApiError")),
        );
    }
    Operation {
        summary: Some(summary.into()),
        tags: tags.iter().map(|t| (*t).to_string()).collect(),
        parameters,
        request_body: request_body.map(|schema| RequestBody {
            content: {
                let mut m = HashMap::new();
                m.insert("application/json".into(), MediaType { schema });
                m
            },
        }),
        responses,
    }
}

fn json_response(description: &str, schema: Schema) -> Response {
    let mut content = HashMap::new();
    content.insert("application/json".into(), MediaType { schema });
    Response {
        description: description.into(),
        content: Some(content),
    }
}

fn schemas() -> HashMap<String, Schema> {
    let mut s = HashMap::new();
    s.insert(
        "QueryRequest".into(),
        object_schema(&[
            ("tracking_no", string_schema()),
            ("carrier_code", string_schema()),
        ]),
    );
    s.insert(
        "TrackingEvent".into(),
        object_schema(&[
            ("occurred_at", string_schema()),
            ("location", string_schema()),
            ("description", string_schema()),
            ("status", string_schema()),
        ]),
    );
    s.insert(
        "TrackingResult".into(),
        object_schema(&[
            ("query_no", string_schema()),
            ("carrier_code", string_schema()),
            ("tracking_no", string_schema()),
            ("status", string_schema()),
            ("delivered_at", string_schema()),
            ("estimated_delivery_at", string_schema()),
            ("latest_description", string_schema()),
            ("raw_status", string_schema()),
            ("events", array_schema(schema_ref("TrackingEvent"))),
        ]),
    );
    s.insert(
        "Carrier".into(),
        object_schema(&[
            ("carrier_code", string_schema()),
            ("channel", string_schema()),
        ]),
    );
    s.insert(
        "SubscribeRequest".into(),
        object_schema(&[
            ("carrier_code", string_schema()),
            ("callback_url", string_schema()),
            ("event_type", string_schema()),
        ]),
    );
    s.insert(
        "SubscriptionResult".into(),
        object_schema(&[
            ("subscription_id", string_schema()),
            ("secret", string_schema()),
        ]),
    );
    s.insert(
        "ApiError".into(),
        object_schema(&[
            ("code", integer_schema()),
            ("message", string_schema()),
            ("error_code", string_schema()),
            ("error_message", string_schema()),
        ]),
    );
    s.insert(
        "TrackingOk".into(),
        object_schema(&[
            ("code", integer_schema()),
            ("message", string_schema()),
            ("data", schema_ref("TrackingResult")),
        ]),
    );
    s.insert(
        "CarriersOk".into(),
        object_schema(&[
            ("code", integer_schema()),
            ("message", string_schema()),
            ("data", array_schema(schema_ref("Carrier"))),
        ]),
    );
    s.insert(
        "SubscribeOk".into(),
        object_schema(&[
            ("code", integer_schema()),
            ("message", string_schema()),
            ("data", schema_ref("SubscriptionResult")),
        ]),
    );
    s
}

fn object_schema(props: &[(&str, Schema)]) -> Schema {
    let properties = props
        .iter()
        .map(|(name, schema)| ((*name).to_string(), schema.clone()))
        .collect();
    Schema {
        schema_type: Some("object".into()),
        properties: Some(properties),
        reference: None,
        items: None,
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn spec_covers_all_endpoints() {
        let json = serde_json::to_value(spec()).unwrap();
        for p in [
            "/v1/tracking/query",
            "/v1/tracking/{query_no}",
            "/v1/carriers",
            "/v1/subscriptions",
            "/v1/health",
        ] {
            let path = format!("/paths/{}", p.replace('/', "~1"));
            assert!(json.pointer(&path).is_some(), "missing {p}");
        }
        // {query_no} 路径参数已文档化
        assert!(
            json.pointer("/paths/~1v1~1tracking~1{query_no}/get/parameters/0/name")
                .is_some()
        );
        // 请求体已文档化
        assert!(
            json.pointer("/paths/~1v1~1tracking~1query/post/requestBody/content/application~1json")
                .is_some()
        );
    }
}

# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
# e-cat 查询网关对外 API 客户端 — 零依赖（stdlib urllib.request）
import json
import urllib.error
import urllib.request


class TrackingApiError(Exception):
    def __init__(self, code, message, error_code=None, error_message=None, http_status=None):
        super().__init__(message)
        self.code = code
        self.message = message
        self.error_code = error_code
        self.error_message = error_message
        self.http_status = http_status


class TrackingClient:
    def __init__(self, api_key, base_url="http://localhost:8080", timeout=10):
        self.base_url = base_url.rstrip("/")
        self.api_key = api_key
        self.timeout = timeout

    def _request(self, method, path, payload=None):
        data = json.dumps(payload).encode("utf-8") if payload is not None else None
        req = urllib.request.Request(self.base_url + path, data=data, method=method)
        req.add_header("X-API-Key", self.api_key)
        if data is not None:
            req.add_header("Content-Type", "application/json")
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                body = _parse_body(resp.read())
                http_status = resp.status
        except urllib.error.HTTPError as e:
            http_status = e.code
            body = _parse_body(e.read())
        except TimeoutError:
            raise TrackingApiError(-1, "request timeout") from None
        except urllib.error.URLError as e:
            raise TrackingApiError(-1, "network error: %s" % e.reason) from None
        if body is None:
            raise TrackingApiError(-1, "invalid response (non-JSON), HTTP %d" % http_status)
        if body.get("code", 0) != 0:
            raise TrackingApiError(
                body.get("code"),
                body.get("message", "HTTP %d" % http_status),
                body.get("error_code"),
                body.get("error_message"),
                http_status,
            )
        return body.get("data")

    def query_tracking(self, tracking_no, carrier_code=None):
        payload = {"tracking_no": tracking_no}
        if carrier_code:
            payload["carrier_code"] = carrier_code
        return self._request("POST", "/v1/tracking/query", payload)

    def get_tracking(self, query_no):
        return self._request("GET", "/v1/tracking/%s" % _quote(query_no))

    def list_carriers(self):
        return self._request("GET", "/v1/carriers")

    def subscribe(self, carrier_code, callback_url, event_type="tracking.update"):
        return self._request("POST", "/v1/subscriptions", {
            "carrier_code": carrier_code,
            "callback_url": callback_url,
            "event_type": event_type,
        })


def _quote(path_seg):
    from urllib.parse import quote
    return quote(path_seg, safe="")


def _parse_body(raw):
    if not raw:
        return None
    try:
        return json.loads(raw.decode("utf-8"))
    except (ValueError, UnicodeDecodeError):
        return None

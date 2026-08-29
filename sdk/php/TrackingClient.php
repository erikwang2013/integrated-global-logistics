#!/usr/bin/env php
<?php
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// e-cat 查询网关对外 API 客户端 — 零依赖（内置 curl）

class TrackingApiError extends Exception {
    public $code;
    public $error_code;
    public $error_message;
    public $http_status;

    public function __construct($code, $message, $error_code = null, $error_message = null, $http_status = null) {
        parent::__construct($message);
        $this->code = $code;
        $this->error_code = $error_code;
        $this->error_message = $error_message;
        $this->http_status = $http_status;
    }
}

class TrackingClient {
    private $base_url;
    private $api_key;
    private $timeout;

    public function __construct($api_key, $base_url = "http://localhost:8080", $timeout = 10) {
        $this->base_url = rtrim($base_url, "/");
        $this->api_key = $api_key;
        $this->timeout = $timeout;
    }

    private function request($method, $path, $payload = null) {
        $ch = curl_init($this->base_url . $path);
        $headers = array("X-API-Key: " . $this->api_key);
        $body = null;
        if ($payload !== null) {
            $body = json_encode($payload);
            $headers[] = "Content-Type: application/json";
        }
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ));
        $raw = curl_exec($ch);
        $http_status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($errno !== 0) {
            throw new TrackingApiError(-1, "network error: " . $error);
        }
        $body_arr = json_decode($raw, true);
        if (!is_array($body_arr)) {
            throw new TrackingApiError(-1, "invalid response (non-JSON), HTTP {$http_status}", null, null, $http_status);
        }
        if ((int) $body_arr["code"] !== 0) {
            throw new TrackingApiError(
                $body_arr["code"],
                isset($body_arr["message"]) ? $body_arr["message"] : "HTTP {$http_status}",
                isset($body_arr["error_code"]) ? $body_arr["error_code"] : null,
                isset($body_arr["error_message"]) ? $body_arr["error_message"] : null,
                $http_status
            );
        }
        return isset($body_arr["data"]) ? $body_arr["data"] : null;
    }

    public function query_tracking($tracking_no, $carrier_code = null) {
        $payload = array("tracking_no" => $tracking_no);
        if ($carrier_code) {
            $payload["carrier_code"] = $carrier_code;
        }
        return $this->request("POST", "/v1/tracking/query", $payload);
    }

    public function get_tracking($query_no) {
        return $this->request("GET", "/v1/tracking/" . rawurlencode($query_no));
    }

    public function list_carriers() {
        return $this->request("GET", "/v1/carriers");
    }

    public function subscribe($carrier_code, $callback_url, $event_type = "tracking.update") {
        return $this->request("POST", "/v1/subscriptions", array(
            "carrier_code" => $carrier_code,
            "callback_url" => $callback_url,
            "event_type" => $event_type,
        ));
    }
}

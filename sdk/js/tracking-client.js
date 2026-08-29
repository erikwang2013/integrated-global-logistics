// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// e-cat 查询网关对外 API 客户端 — 零依赖（Node 18+ 内置 fetch）
"use strict";

class TrackingApiError extends Error {
  constructor(code, message, { errorCode = null, errorMessage = null, httpStatus = null } = {}) {
    super(message);
    this.name = "TrackingApiError";
    this.code = code;
    this.errorCode = errorCode;
    this.errorMessage = errorMessage;
    this.httpStatus = httpStatus;
  }
}

class TrackingClient {
  constructor(apiKey, baseUrl = "http://localhost:8080", timeout = 10) {
    this.baseUrl = String(baseUrl).replace(/\/+$/, "");
    this.apiKey = apiKey;
    this.timeout = timeout;
  }

  async request(method, path, payload) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeout * 1000);
    let resp;
    try {
      resp = await fetch(this.baseUrl + path, {
        method,
        headers: {
          "X-API-Key": this.apiKey,
          ...(payload !== undefined ? { "Content-Type": "application/json" } : {}),
        },
        body: payload !== undefined ? JSON.stringify(payload) : undefined,
        signal: controller.signal,
      });
    } catch (err) {
      const msg = err.name === "AbortError" ? "request timeout" : err.message;
      throw new TrackingApiError(-1, "network error: " + msg);
    } finally {
      clearTimeout(timer);
    }
    let body = null;
    try {
      body = await resp.json();
    } catch (_) {
      // non-JSON body
    }
    if (body === null) {
      throw new TrackingApiError(-1, "invalid response (non-JSON), HTTP " + resp.status, { httpStatus: resp.status });
    }
    if (body.code !== 0) {
      throw new TrackingApiError(body.code, body.message || "HTTP " + resp.status, {
        errorCode: body.error_code || null,
        errorMessage: body.error_message || null,
        httpStatus: resp.status,
      });
    }
    return body.data;
  }

  queryTracking(trackingNo, carrierCode) {
    const payload = { tracking_no: trackingNo };
    if (carrierCode) payload.carrier_code = carrierCode;
    return this.request("POST", "/v1/tracking/query", payload);
  }

  getTracking(queryNo) {
    return this.request("GET", "/v1/tracking/" + encodeURIComponent(queryNo));
  }

  listCarriers() {
    return this.request("GET", "/v1/carriers");
  }

  subscribe(carrierCode, callbackUrl, eventType = "tracking.update") {
    return this.request("POST", "/v1/subscriptions", {
      carrier_code: carrierCode,
      callback_url: callbackUrl,
      event_type: eventType,
    });
  }
}

module.exports = { TrackingClient, TrackingApiError };

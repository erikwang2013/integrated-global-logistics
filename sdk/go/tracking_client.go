// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// e-cat 查询网关对外 API 客户端 — 零第三方依赖（stdlib net/http）
package trackingclient

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"time"
)

// TrackingApiError 为网关业务错误或网络失败；Code=-1 表示网络层错误。
type TrackingApiError struct {
	Code         int
	Message      string
	ErrorCode    string
	ErrorMessage string
	HTTPStatus   int
}

func (e *TrackingApiError) Error() string {
	return fmt.Sprintf("code=%d message=%s", e.Code, e.Message)
}

// Client 封装网关四个端点，方法返回信封 data 的原始 JSON（json.RawMessage）。
type Client struct {
	apiKey  string
	baseURL string
	http    *http.Client
}

// NewClient 构造客户端；baseURL 缺省 http://localhost:8080。
func NewClient(apiKey, baseURL string) *Client {
	if baseURL == "" {
		baseURL = "http://localhost:8080"
	}
	return &Client{
		apiKey:  apiKey,
		baseURL: strings.TrimRight(baseURL, "/"),
		http:    &http.Client{Timeout: 10 * time.Second},
	}
}

func (c *Client) do(method, path string, payload any) (json.RawMessage, error) {
	var body io.Reader
	if payload != nil {
		b, err := json.Marshal(payload)
		if err != nil {
			return nil, err
		}
		body = bytes.NewReader(b)
	}
	req, err := http.NewRequest(method, c.baseURL+path, body)
	if err != nil {
		return nil, err
	}
	req.Header.Set("X-API-Key", c.apiKey)
	if payload != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	resp, err := c.http.Do(req)
	if err != nil {
		return nil, &TrackingApiError{Code: -1, Message: "network error: " + err.Error()}
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(resp.Body)
	var env struct {
		Code         int             `json:"code"`
		Message      string          `json:"message"`
		ErrorCode    string          `json:"error_code"`
		ErrorMessage string          `json:"error_message"`
		Data         json.RawMessage `json:"data"`
	}
	if err := json.Unmarshal(raw, &env); err != nil {
		return nil, &TrackingApiError{Code: -1, Message: "invalid response (non-JSON)", HTTPStatus: resp.StatusCode}
	}
	if env.Code != 0 {
		return nil, &TrackingApiError{Code: env.Code, Message: env.Message, ErrorCode: env.ErrorCode, ErrorMessage: env.ErrorMessage, HTTPStatus: resp.StatusCode}
	}
	return env.Data, nil
}

// QueryTracking 轨迹查询，carrierCode 可为空。
func (c *Client) QueryTracking(trackingNo, carrierCode string) (json.RawMessage, error) {
	payload := map[string]string{"tracking_no": trackingNo}
	if carrierCode != "" {
		payload["carrier_code"] = carrierCode
	}
	return c.do("POST", "/v1/tracking/query", payload)
}

// GetTracking 按查询号取上次结果。
func (c *Client) GetTracking(queryNo string) (json.RawMessage, error) {
	return c.do("GET", "/v1/tracking/"+url.PathEscape(queryNo), nil)
}

// ListCarriers 承运商清单。
func (c *Client) ListCarriers() (json.RawMessage, error) {
	return c.do("GET", "/v1/carriers", nil)
}

// Subscribe 注册回调订阅，返回 {subscription_id, secret}。
func (c *Client) Subscribe(carrierCode, callbackURL, eventType string) (json.RawMessage, error) {
	if eventType == "" {
		eventType = "tracking.update"
	}
	return c.do("POST", "/v1/subscriptions", map[string]string{
		"carrier_code": carrierCode,
		"callback_url": callbackURL,
		"event_type":   eventType,
	})
}

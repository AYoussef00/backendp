package api

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"time"
)

type Config struct {
	PanelURL    string
	AgentID     string
	AgentSecret string
}

func LoadConfig() Config {
	return Config{
		PanelURL:    getenvFirst([]string{"SHD_URL", "ZYROX_PANEL_URL"}, "http://localhost:8000"),
		AgentID:     getenvFirst([]string{"SHD_ID", "ZYROX_AGENT_ID"}, ""),
		AgentSecret: getenvFirst([]string{"SHD_SECRET", "ZYROX_AGENT_SECRET"}, ""),
	}
}

func getenvFirst(keys []string, fallback string) string {
	for _, key := range keys {
		if v := os.Getenv(key); v != "" {
			return v
		}
	}
	return fallback
}

type Client struct {
	cfg    Config
	client *http.Client
}

func NewClient(cfg Config) *Client {
	return &Client{
		cfg: cfg,
		client: &http.Client{
			Timeout: 30 * time.Second,
		},
	}
}

func (c *Client) Heartbeat(hostname, version string) error {
	payload := map[string]any{
		"hostname":      hostname,
		"agent_version": version,
		"timestamp":     time.Now().UTC().Format(time.RFC3339),
	}
	_, err := c.post("/api/agent/v1/heartbeat", payload)
	return err
}

func (c *Client) Discovery(payload map[string]any) error {
	_, err := c.post("/api/agent/v1/discovery", payload)
	return err
}

func (c *Client) Websites(websites []map[string]any) error {
	_, err := c.post("/api/agent/v1/websites", map[string]any{"websites": websites})
	return err
}

func (c *Client) Metrics(payload map[string]any) error {
	_, err := c.post("/api/agent/v1/metrics", payload)
	return err
}

func (c *Client) NextJob() (map[string]any, error) {
	body, err := c.get("/api/agent/v1/jobs")
	if err != nil {
		return nil, err
	}
	var resp struct {
		Success bool `json:"success"`
		Data    struct {
			Job map[string]any `json:"job"`
		} `json:"data"`
	}
	if err := json.Unmarshal(body, &resp); err != nil {
		return nil, err
	}
	return resp.Data.Job, nil
}

func (c *Client) JobResult(jobID any, success bool, result map[string]any, errCode, errMsg string) error {
	payload := map[string]any{
		"success": success,
		"result":  result,
	}
	if !success {
		payload["error"] = map[string]string{"code": errCode, "message": errMsg}
	}
	_, err := c.post(fmt.Sprintf("/api/agent/v1/jobs/%v/result", jobID), payload)
	return err
}

func (c *Client) get(path string) ([]byte, error) {
	req, err := http.NewRequest(http.MethodGet, c.cfg.PanelURL+path, nil)
	if err != nil {
		return nil, err
	}
	c.auth(req)
	res, err := c.client.Do(req)
	if err != nil {
		return nil, err
	}
	defer res.Body.Close()
	return io.ReadAll(res.Body)
}

func (c *Client) post(path string, payload any) ([]byte, error) {
	raw, err := json.Marshal(payload)
	if err != nil {
		return nil, err
	}
	req, err := http.NewRequest(http.MethodPost, c.cfg.PanelURL+path, bytes.NewReader(raw))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	c.auth(req)
	res, err := c.client.Do(req)
	if err != nil {
		return nil, err
	}
	defer res.Body.Close()
	body, _ := io.ReadAll(res.Body)
	if res.StatusCode >= 400 {
		return body, fmt.Errorf("http %d: %s", res.StatusCode, string(body))
	}
	return body, nil
}

func (c *Client) auth(req *http.Request) {
	req.Header.Set("X-Agent-Id", c.cfg.AgentID)
	req.Header.Set("X-Agent-Secret", c.cfg.AgentSecret)
	req.Header.Set("Accept", "application/json")
}

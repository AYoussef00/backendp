package jobs

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"

	"github.com/zyrox/zyrox-agent/internal/api"
	"github.com/zyrox/zyrox-agent/internal/apache"
	"github.com/zyrox/zyrox-agent/internal/filesystem"
	"github.com/zyrox/zyrox-agent/internal/nginx"
	"github.com/zyrox/zyrox-agent/internal/security"
	"github.com/zyrox/zyrox-agent/internal/system"
)

type Runner struct {
	client  *api.Client
	version string
}

func NewRunner(client *api.Client, version string) *Runner {
	return &Runner{client: client, version: version}
}

func (r *Runner) RunForever() {
	tickerHB := time.NewTicker(30 * time.Second)
	tickerJobs := time.NewTicker(10 * time.Second)
	tickerMetrics := time.NewTicker(60 * time.Second)
	defer tickerHB.Stop()
	defer tickerJobs.Stop()
	defer tickerMetrics.Stop()

	_ = r.client.Heartbeat(system.Hostname(), r.version)
	_ = r.client.Discovery(system.Discover())
	_ = r.client.Websites(append(nginx.DiscoverSites(), apache.DiscoverSites()...))

	for {
		select {
		case <-tickerHB.C:
			_ = r.client.Heartbeat(system.Hostname(), r.version)
		case <-tickerMetrics.C:
			_ = r.client.Metrics(system.Metrics())
		case <-tickerJobs.C:
			r.pollOnce()
		}
	}
}

func (r *Runner) pollOnce() {
	job, err := r.client.NextJob()
	if err != nil || job == nil {
		return
	}
	jobID := job["id"]
	jobType, _ := job["type"].(string)
	payload, _ := job["payload"].(map[string]any)

	if err := security.ValidateCommand(jobType); err != nil {
		_ = r.client.JobResult(jobID, false, nil, "JOB_NOT_ALLOWED", err.Error())
		return
	}

	result, errCode, errMsg, ok := r.execute(jobType, payload)
	if ok {
		_ = r.client.JobResult(jobID, true, result, "", "")
	} else {
		_ = r.client.JobResult(jobID, false, result, errCode, errMsg)
	}
}

func (r *Runner) execute(jobType string, payload map[string]any) (map[string]any, string, string, bool) {
	switch jobType {
	case "discover_server":
		report := system.Discover()
		_ = r.client.Discovery(report)
		return report, "", "", true
	case "discover_websites":
		sites := append(nginx.DiscoverSites(), apache.DiscoverSites()...)
		_ = r.client.Websites(sites)
		return map[string]any{"count": len(sites), "websites": sites}, "", "", true
	case "list_files":
		path, _ := payload["path"].(string)
		entries, err := filesystem.List(path)
		if err != nil {
			return nil, err.Error(), err.Error(), false
		}
		return map[string]any{"entries": entries}, "", "", true
	case "read_file":
		path, _ := payload["path"].(string)
		content, err := filesystem.Read(path)
		if err != nil {
			return nil, err.Error(), err.Error(), false
		}
		return map[string]any{"content": content}, "", "", true
	case "write_file":
		path, _ := payload["path"].(string)
		content, _ := payload["content"].(string)
		if err := filesystem.Write(path, content); err != nil {
			return nil, err.Error(), err.Error(), false
		}
		return map[string]any{"written": true}, "", "", true
	case "delete_file":
		path, _ := payload["path"].(string)
		if err := filesystem.Delete(path); err != nil {
			return nil, err.Error(), err.Error(), false
		}
		return map[string]any{"deleted": true}, "", "", true
	case "create_directory":
		path, _ := payload["path"].(string)
		if err := filesystem.Mkdir(path); err != nil {
			return nil, err.Error(), err.Error(), false
		}
		return map[string]any{"created": true}, "", "", true
	case "rename_file":
		from, _ := payload["from"].(string)
		to, _ := payload["to"].(string)
		if err := filesystem.Rename(from, to); err != nil {
			return nil, err.Error(), err.Error(), false
		}
		return map[string]any{"renamed": true}, "", "", true
	case "get_metrics":
		return system.Metrics(), "", "", true
	case "get_logs":
		return readLogs(payload)
	case "website_enable", "website_start":
		return websiteToggle(payload, true)
	case "website_disable", "website_stop":
		return websiteToggle(payload, false)
	case "website_restart":
		if _, code, msg, ok := websiteToggle(payload, false); !ok {
			return nil, code, msg, false
		}
		return websiteToggle(payload, true)
	case "service_start", "service_stop", "service_restart", "service_reload", "service_status":
		return serviceAction(jobType, payload)
	case "nginx_test":
		return runAllowlisted("nginx", "-t")
	case "nginx_reload":
		if _, code, msg, ok := runAllowlisted("nginx", "-t"); !ok {
			return nil, code, msg, false
		}
		return runAllowlisted("systemctl", "reload", "nginx")
	default:
		return nil, "JOB_NOT_ALLOWED", "unknown command", false
	}
}

func websiteToggle(payload map[string]any, enable bool) (map[string]any, string, string, bool) {
	webserver, _ := payload["webserver"].(string)
	configPath, _ := payload["config_path"].(string)
	if webserver == "" {
		webserver = "nginx"
	}
	if configPath == "" {
		return nil, "FILE_NOT_FOUND", "missing config_path", false
	}

	base := filepath.Base(configPath)
	switch webserver {
	case "nginx":
		enabled := "/etc/nginx/sites-enabled/" + base
		available := "/etc/nginx/sites-available/" + base
		if enable {
			if _, err := os.Stat(enabled); err == nil {
				return map[string]any{"enabled": true}, "", "", true
			}
			src := available
			if _, err := os.Stat(configPath); err == nil {
				src = configPath
			}
			if err := os.Symlink(src, enabled); err != nil && !os.IsExist(err) {
				return nil, "PERMISSION_DENIED", err.Error(), false
			}
		} else {
			_ = os.Remove(enabled)
		}
		if _, code, msg, ok := runAllowlisted("nginx", "-t"); !ok {
			return nil, code, msg, false
		}
		return runAllowlisted("systemctl", "reload", "nginx")
	case "apache":
		if enable {
			_, code, msg, ok := runAllowlisted("a2ensite", base)
			if !ok {
				return nil, code, msg, false
			}
		} else {
			_, code, msg, ok := runAllowlisted("a2dissite", base)
			if !ok {
				return nil, code, msg, false
			}
		}
		return runAllowlisted("systemctl", "reload", "apache2")
	default:
		return nil, "JOB_NOT_ALLOWED", "unsupported webserver", false
	}
}

func serviceAction(jobType string, payload map[string]any) (map[string]any, string, string, bool) {
	service, _ := payload["service"].(string)
	if err := security.ValidateService(service); err != nil {
		return nil, "SERVICE_NOT_FOUND", err.Error(), false
	}
	action := strings.TrimPrefix(jobType, "service_")
	return runAllowlisted("systemctl", action, service)
}

func runAllowlisted(bin string, args ...string) (map[string]any, string, string, bool) {
	cmd := exec.Command(bin, args...)
	out, err := cmd.CombinedOutput()
	result := map[string]any{"output": truncate(string(out), 8000)}
	if err != nil {
		code := "OPERATION_FAILED"
		if strings.Contains(string(out), "invalid") || strings.Contains(string(out), "failed") {
			if bin == "nginx" {
				code = "NGINX_CONFIG_INVALID"
			}
		}
		return result, code, strings.TrimSpace(string(out)), false
	}
	return result, "", "", true
}

func readLogs(payload map[string]any) (map[string]any, string, string, bool) {
	source, _ := payload["source"].(string)
	lines := 200
	if v, ok := payload["lines"].(float64); ok {
		lines = int(v)
	}
	path := map[string]string{
		"nginx_access":  "/var/log/nginx/access.log",
		"nginx_error":   "/var/log/nginx/error.log",
		"apache_access": "/var/log/apache2/access.log",
		"apache_error":  "/var/log/apache2/error.log",
		"php_fpm":       "/var/log/php8.3-fpm.log",
		"agent":         "/var/log/zyrox-agent/agent.log",
		"syslog":        "/var/log/syslog",
	}[source]
	if path == "" {
		return nil, "JOB_NOT_ALLOWED", "unknown log source", false
	}
	out, err := exec.Command("tail", "-n", fmt.Sprintf("%d", lines), path).CombinedOutput()
	if err != nil {
		return nil, "FILE_NOT_FOUND", err.Error(), false
	}
	return map[string]any{"lines": strings.Split(strings.TrimRight(string(out), "\n"), "\n")}, "", "", true
}

func truncate(s string, n int) string {
	if len(s) <= n {
		return s
	}
	return s[:n]
}

package system

import (
	"os"
	"os/exec"
	"runtime"
	"strconv"
	"strings"
)

func Hostname() string {
	h, _ := os.Hostname()
	return h
}

func Discover() map[string]any {
	report := map[string]any{
		"hostname": Hostname(),
		"os": map[string]any{
			"name":    detectOSName(),
			"version": detectOSVersion(),
			"kernel":  runtime.GOOS + "/" + runtime.GOARCH,
		},
		"cpu": map[string]any{
			"cores": runtime.NumCPU(),
		},
		"memory": map[string]any{
			"total": memTotal(),
		},
		"disk": map[string]any{
			"total": diskTotal(),
		},
		"webservers": detectWebservers(),
		"php":        detectPHP(),
		"services":   detectServices(),
	}
	return report
}

func detectOSName() string {
	data, err := os.ReadFile("/etc/os-release")
	if err != nil {
		return runtime.GOOS
	}
	for _, line := range strings.Split(string(data), "\n") {
		if strings.HasPrefix(line, "NAME=") {
			return strings.Trim(strings.TrimPrefix(line, "NAME="), "\"")
		}
	}
	return runtime.GOOS
}

func detectOSVersion() string {
	data, err := os.ReadFile("/etc/os-release")
	if err != nil {
		return ""
	}
	for _, line := range strings.Split(string(data), "\n") {
		if strings.HasPrefix(line, "VERSION_ID=") {
			return strings.Trim(strings.TrimPrefix(line, "VERSION_ID="), "\"")
		}
	}
	return ""
}

func memTotal() uint64 {
	data, err := os.ReadFile("/proc/meminfo")
	if err != nil {
		return 0
	}
	for _, line := range strings.Split(string(data), "\n") {
		if strings.HasPrefix(line, "MemTotal:") {
			fields := strings.Fields(line)
			if len(fields) >= 2 {
				kb, _ := strconv.ParseUint(fields[1], 10, 64)
				return kb * 1024
			}
		}
	}
	return 0
}

func diskTotal() uint64 {
	out, err := exec.Command("df", "-B1", "/").Output()
	if err != nil {
		return 0
	}
	lines := strings.Split(strings.TrimSpace(string(out)), "\n")
	if len(lines) < 2 {
		return 0
	}
	fields := strings.Fields(lines[1])
	if len(fields) < 2 {
		return 0
	}
	v, _ := strconv.ParseUint(fields[1], 10, 64)
	return v
}

func which(bin string) bool {
	_, err := exec.LookPath(bin)
	return err == nil
}

func detectWebservers() []map[string]any {
	var list []map[string]any
	if which("nginx") {
		list = append(list, map[string]any{"type": "nginx", "version": versionOf("nginx", "-v")})
	}
	if which("apache2") || which("httpd") {
		bin := "apache2"
		if which("httpd") {
			bin = "httpd"
		}
		list = append(list, map[string]any{"type": "apache", "version": versionOf(bin, "-v")})
	}
	return list
}

func detectPHP() []string {
	var versions []string
	out, err := exec.Command("bash", "-lc", "ls /usr/bin/php* 2>/dev/null | xargs -n1 basename").Output()
	if err != nil {
		if which("php") {
			return []string{versionOf("php", "-v")}
		}
		return versions
	}
	for _, line := range strings.Split(strings.TrimSpace(string(out)), "\n") {
		if strings.HasPrefix(line, "php") {
			versions = append(versions, line)
		}
	}
	return versions
}

func detectServices() []map[string]any {
	names := []string{"nginx", "apache2", "mysql", "mariadb", "redis-server", "supervisor", "docker"}
	var result []map[string]any
	for _, name := range names {
		status := "unknown"
		enabled := false
		out, err := exec.Command("systemctl", "is-active", name).Output()
		if err == nil {
			status = strings.TrimSpace(string(out))
		}
		en, err := exec.Command("systemctl", "is-enabled", name).Output()
		if err == nil && strings.TrimSpace(string(en)) == "enabled" {
			enabled = true
		}
		if status != "unknown" || enabled {
			result = append(result, map[string]any{
				"name":    name,
				"status":  status,
				"enabled": enabled,
			})
		}
	}
	return result
}

func versionOf(bin string, arg string) string {
	out, err := exec.Command(bin, arg).CombinedOutput()
	if err != nil && len(out) == 0 {
		return ""
	}
	line := strings.Split(string(out), "\n")[0]
	return strings.TrimSpace(line)
}

func Metrics() map[string]any {
	return map[string]any{
		"cpu_percent":    cpuPercent(),
		"memory_percent": memPercent(),
		"disk_percent":   diskPercent(),
		"load_1":         loadAvg(0),
		"load_5":         loadAvg(1),
		"load_15":        loadAvg(2),
		"uptime_seconds": uptime(),
	}
}

func cpuPercent() float64 {
	// Lightweight approximation using load/cores.
	load := loadAvg(0)
	cores := float64(runtime.NumCPU())
	if cores == 0 {
		return 0
	}
	pct := (load / cores) * 100
	if pct > 100 {
		return 100
	}
	return pct
}

func memPercent() float64 {
	data, err := os.ReadFile("/proc/meminfo")
	if err != nil {
		return 0
	}
	var total, available float64
	for _, line := range strings.Split(string(data), "\n") {
		fields := strings.Fields(line)
		if len(fields) < 2 {
			continue
		}
		val, _ := strconv.ParseFloat(fields[1], 64)
		switch fields[0] {
		case "MemTotal:":
			total = val
		case "MemAvailable:":
			available = val
		}
	}
	if total == 0 {
		return 0
	}
	return ((total - available) / total) * 100
}

func diskPercent() float64 {
	out, err := exec.Command("df", "-P", "/").Output()
	if err != nil {
		return 0
	}
	lines := strings.Split(strings.TrimSpace(string(out)), "\n")
	if len(lines) < 2 {
		return 0
	}
	fields := strings.Fields(lines[1])
	if len(fields) < 5 {
		return 0
	}
	return parsePercent(fields[4])
}

func parsePercent(v string) float64 {
	v = strings.TrimSuffix(v, "%")
	f, _ := strconv.ParseFloat(v, 64)
	return f
}

func loadAvg(idx int) float64 {
	data, err := os.ReadFile("/proc/loadavg")
	if err != nil {
		return 0
	}
	fields := strings.Fields(string(data))
	if len(fields) <= idx {
		return 0
	}
	f, _ := strconv.ParseFloat(fields[idx], 64)
	return f
}

func uptime() uint64 {
	data, err := os.ReadFile("/proc/uptime")
	if err != nil {
		return 0
	}
	fields := strings.Fields(string(data))
	if len(fields) == 0 {
		return 0
	}
	f, _ := strconv.ParseFloat(fields[0], 64)
	return uint64(f)
}

package nginx

import (
	"os"
	"path/filepath"
	"regexp"
	"strings"
)

func DiscoverSites() []map[string]any {
	dirs := []string{"/etc/nginx/sites-enabled", "/etc/nginx/conf.d"}
	var sites []map[string]any
	seen := map[string]bool{}

	for _, dir := range dirs {
		entries, err := os.ReadDir(dir)
		if err != nil {
			continue
		}
		for _, entry := range entries {
			if entry.IsDir() {
				continue
			}
			path := filepath.Join(dir, entry.Name())
			content, err := os.ReadFile(path)
			if err != nil {
				continue
			}
			for _, site := range parseServerBlocks(string(content), path) {
				key := site["domain"].(string) + "|" + path
				if seen[key] {
					continue
				}
				seen[key] = true
				sites = append(sites, site)
			}
		}
	}
	return sites
}

func parseServerBlocks(content, configPath string) []map[string]any {
	reServer := regexp.MustCompile(`(?s)server\s*\{(.*?)\}`)
	reName := regexp.MustCompile(`(?m)^\s*server_name\s+([^;]+);`)
	reRoot := regexp.MustCompile(`(?m)^\s*root\s+([^;]+);`)
	reListen := regexp.MustCompile(`(?m)^\s*listen\s+([^;]+);`)
	reSSL := regexp.MustCompile(`ssl_certificate`)
	reProxy := regexp.MustCompile(`(?m)^\s*proxy_pass\s+([^;]+);`)

	var results []map[string]any
	matches := reServer.FindAllStringSubmatch(content, -1)
	for _, match := range matches {
		block := match[1]
		nameMatch := reName.FindStringSubmatch(block)
		if nameMatch == nil {
			continue
		}
		names := strings.Fields(nameMatch[1])
		if len(names) == 0 || names[0] == "_" {
			continue
		}
		primary := names[0]
		aliases := []string{}
		if len(names) > 1 {
			aliases = names[1:]
		}
		root := ""
		if m := reRoot.FindStringSubmatch(block); m != nil {
			root = strings.TrimSpace(m[1])
		}
		ssl := reSSL.MatchString(block) || strings.Contains(block, "listen 443")
		status := "active"
		if strings.Contains(configPath, "sites-available") && !strings.Contains(configPath, "sites-enabled") {
			status = "disabled"
		}
		site := map[string]any{
			"domain":       primary,
			"aliases":      aliases,
			"root_path":    root,
			"config_path":  configPath,
			"webserver":    "nginx",
			"ssl":          ssl,
			"status":       status,
			"listen":       reListen.FindAllString(block, -1),
			"proxy_pass":   first(reProxy.FindStringSubmatch(block)),
			"framework":    detectFramework(root),
		}
		results = append(results, site)
	}
	return results
}

func first(m []string) string {
	if len(m) > 1 {
		return strings.TrimSpace(m[1])
	}
	return ""
}

func detectFramework(root string) string {
	if root == "" {
		return ""
	}
	candidates := []string{root, filepath.Dir(root)}
	for _, base := range candidates {
		if fileExists(filepath.Join(base, "artisan")) && fileExists(filepath.Join(base, "composer.json")) {
			return "laravel"
		}
	}
	return ""
}

func fileExists(path string) bool {
	_, err := os.Stat(path)
	return err == nil
}

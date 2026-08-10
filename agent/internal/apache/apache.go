package apache

import (
	"os"
	"path/filepath"
	"regexp"
	"strings"
)

func DiscoverSites() []map[string]any {
	dirs := []string{"/etc/apache2/sites-enabled", "/etc/httpd/conf.d"}
	var sites []map[string]any

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
			sites = append(sites, parseVHosts(string(content), path)...)
		}
	}
	return sites
}

func parseVHosts(content, configPath string) []map[string]any {
	reVHost := regexp.MustCompile(`(?is)<VirtualHost[^>]*>(.*?)</VirtualHost>`)
	reServer := regexp.MustCompile(`(?im)^\s*ServerName\s+(\S+)`)
	reAlias := regexp.MustCompile(`(?im)^\s*ServerAlias\s+(.+)$`)
	reRoot := regexp.MustCompile(`(?im)^\s*DocumentRoot\s+(\S+)`)
	reSSL := regexp.MustCompile(`(?i)SSLEngine\s+on`)

	var results []map[string]any
	for _, match := range reVHost.FindAllStringSubmatch(content, -1) {
		block := match[1]
		nameMatch := reServer.FindStringSubmatch(block)
		if nameMatch == nil {
			continue
		}
		primary := nameMatch[1]
		aliases := []string{}
		if m := reAlias.FindStringSubmatch(block); m != nil {
			aliases = strings.Fields(m[1])
		}
		root := ""
		if m := reRoot.FindStringSubmatch(block); m != nil {
			root = m[1]
		}
		results = append(results, map[string]any{
			"domain":      primary,
			"aliases":     aliases,
			"root_path":   root,
			"config_path": configPath,
			"webserver":   "apache",
			"ssl":         reSSL.MatchString(block),
			"status":      "active",
		})
	}
	return results
}

package security

import (
	"fmt"
	"path/filepath"
	"strings"
)

var allowedRoots = []string{"/var/www", "/home", "/srv", "/opt"}
var blockedPrefixes = []string{"/etc/shadow", "/etc/ssh", "/root", "/proc", "/sys", "/boot"}

func ValidatePath(path string) (string, error) {
	if path == "" {
		return "", fmt.Errorf("PATH_NOT_ALLOWED")
	}
	clean := filepath.Clean(path)
	if !filepath.IsAbs(clean) {
		return "", fmt.Errorf("PATH_NOT_ALLOWED")
	}
	if clean == "/" {
		return "", fmt.Errorf("PATH_NOT_ALLOWED")
	}
	for _, blocked := range blockedPrefixes {
		if clean == blocked || strings.HasPrefix(clean, blocked+"/") {
			return "", fmt.Errorf("PATH_NOT_ALLOWED")
		}
	}
	ok := false
	for _, root := range allowedRoots {
		if clean == root || strings.HasPrefix(clean, root+"/") {
			ok = true
			break
		}
	}
	if !ok {
		return "", fmt.Errorf("PATH_NOT_ALLOWED")
	}
	return clean, nil
}

var allowedServices = map[string]bool{
	"nginx": true, "apache2": true, "httpd": true, "mysql": true, "mariadb": true,
	"redis": true, "redis-server": true, "supervisor": true, "docker": true,
	"php-fpm": true, "php8.2-fpm": true, "php8.3-fpm": true, "php8.4-fpm": true,
}

func ValidateService(name string) error {
	if !allowedServices[name] {
		return fmt.Errorf("SERVICE_NOT_FOUND")
	}
	return nil
}

var allowedCommands = map[string]bool{
	"discover_server": true, "discover_websites": true, "list_files": true, "read_file": true,
	"write_file": true, "delete_file": true, "create_directory": true, "rename_file": true,
	"website_start": true, "website_stop": true, "website_restart": true, "website_enable": true,
	"website_disable": true, "service_start": true, "service_stop": true, "service_restart": true,
	"service_reload": true, "service_status": true, "get_logs": true, "get_metrics": true,
	"nginx_test": true, "nginx_reload": true,
}

func ValidateCommand(name string) error {
	if !allowedCommands[name] {
		return fmt.Errorf("JOB_NOT_ALLOWED")
	}
	return nil
}

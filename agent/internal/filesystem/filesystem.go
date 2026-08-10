package filesystem

import (
	"fmt"
	"os"
	"path/filepath"
	"time"

	"github.com/zyrox/zyrox-agent/internal/security"
)

func List(path string) ([]map[string]any, error) {
	clean, err := security.ValidatePath(path)
	if err != nil {
		return nil, err
	}
	entries, err := os.ReadDir(clean)
	if err != nil {
		return nil, fmt.Errorf("FILE_NOT_FOUND")
	}
	var result []map[string]any
	for _, entry := range entries {
		info, err := entry.Info()
		if err != nil {
			continue
		}
		typ := "file"
		if entry.IsDir() {
			typ = "dir"
		}
		result = append(result, map[string]any{
			"name": entry.Name(),
			"path": filepath.Join(clean, entry.Name()),
			"type": typ,
			"size": info.Size(),
			"mode": fmt.Sprintf("%o", info.Mode().Perm()),
			"mod":  info.ModTime().UTC().Format(time.RFC3339),
		})
	}
	return result, nil
}

func Read(path string) (string, error) {
	clean, err := security.ValidatePath(path)
	if err != nil {
		return "", err
	}
	data, err := os.ReadFile(clean)
	if err != nil {
		return "", fmt.Errorf("FILE_NOT_FOUND")
	}
	if len(data) > 1024*1024 {
		return "", fmt.Errorf("FILE_TOO_LARGE")
	}
	return string(data), nil
}

func Write(path, content string) error {
	clean, err := security.ValidatePath(path)
	if err != nil {
		return err
	}
	if len(content) > 1024*1024 {
		return fmt.Errorf("FILE_TOO_LARGE")
	}
	return os.WriteFile(clean, []byte(content), 0644)
}

func Delete(path string) error {
	clean, err := security.ValidatePath(path)
	if err != nil {
		return err
	}
	return os.RemoveAll(clean)
}

func Mkdir(path string) error {
	clean, err := security.ValidatePath(path)
	if err != nil {
		return err
	}
	return os.MkdirAll(clean, 0755)
}

func Rename(from, to string) error {
	src, err := security.ValidatePath(from)
	if err != nil {
		return err
	}
	dst, err := security.ValidatePath(to)
	if err != nil {
		return err
	}
	return os.Rename(src, dst)
}

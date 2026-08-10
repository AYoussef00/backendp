package main

import (
	"fmt"
	"os"

	"github.com/zyrox/zyrox-agent/internal/api"
	"github.com/zyrox/zyrox-agent/internal/jobs"
	"github.com/zyrox/zyrox-agent/internal/system"
)

const version = "1.0.0"

func main() {
	if len(os.Args) < 2 {
		fmt.Println("usage: zyrox-agent <run|status|discovery|test|version>")
		os.Exit(1)
	}

	switch os.Args[1] {
	case "version":
		fmt.Println(version)
	case "status":
		cfg := api.LoadConfig()
		fmt.Printf("panel=%s agent_id=%s version=%s\n", cfg.PanelURL, cfg.AgentID, version)
	case "discovery":
		report := system.Discover()
		fmt.Printf("%+v\n", report)
	case "test":
		cfg := api.LoadConfig()
		client := api.NewClient(cfg)
		if err := client.Heartbeat(system.Hostname(), version); err != nil {
			fmt.Println("heartbeat failed:", err)
			os.Exit(1)
		}
		fmt.Println("ok")
	case "run":
		cfg := api.LoadConfig()
		client := api.NewClient(cfg)
		runner := jobs.NewRunner(client, version)
		runner.RunForever()
	default:
		fmt.Println("unknown command")
		os.Exit(1)
	}
}

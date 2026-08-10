#!/usr/bin/env bash
# Local helper to build agent binaries for linux.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
GOOS=linux GOARCH=amd64 go build -o dist/zyrox-agent-linux-amd64 ./cmd/zyrox-agent
GOOS=linux GOARCH=arm64 go build -o dist/zyrox-agent-linux-arm64 ./cmd/zyrox-agent
echo "Built agent binaries in agent/dist"

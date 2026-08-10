# Agent

Go binary under `agent/`.

## Commands

```bash
zyrox-agent run
zyrox-agent status
zyrox-agent discovery
zyrox-agent test
zyrox-agent version
```

## Security model

- Dedicated `zyrox` user
- Restricted sudoers (no `ALL`)
- Command allowlist enforced in agent code
- Path sandbox for file operations
- No arbitrary shell execution from API payloads

## Protocol

Auth headers:

- `X-Agent-Id`
- `X-Agent-Secret`

Endpoints:

- `POST /api/agent/v1/register`
- `POST /api/agent/v1/heartbeat`
- `POST /api/agent/v1/discovery`
- `POST /api/agent/v1/websites`
- `POST /api/agent/v1/metrics`
- `GET /api/agent/v1/jobs`
- `POST /api/agent/v1/jobs/{id}/result`

# Agent

Go binary under `agent/`. On target hosts it is installed as `syshealthd`.

## Commands

```bash
syshealthd run
syshealthd status
syshealthd discovery
syshealthd test
syshealthd version
```

## Security model

- Dedicated `syshealth` user
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

# API

## Agent API

All authenticated agent routes require `X-Agent-Id` + `X-Agent-Secret`.

Responses:

```json
{ "success": true, "data": {} }
```

```json
{
  "success": false,
  "error": { "code": "AGENT_UNAUTHORIZED", "message": "..." }
}
```

### Register

`POST /api/agent/v1/register`

Body: `installation_token`, optional `hostname`, `agent_version`, `os`.

Returns one-time exchange for durable `agent_id` + `agent_secret`.

### Heartbeat

`POST /api/agent/v1/heartbeat`

### Jobs

`GET /api/agent/v1/jobs` claims next pending job.

`POST /api/agent/v1/jobs/{job}/result` submits success/failure.

## Dashboard routes

Inertia/web routes under auth:

- `/servers`, `/websites`, `/jobs`
- website actions queue `ServerJob` records
- file/log operations are job-backed

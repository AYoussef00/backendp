# Architecture

ZYROX uses an agent-first control plane.

```text
Laravel Control Plane  --HTTPS API-->  ZYROX Agents (outbound only)
```

## Components

1. **Control Plane** (`app/`): organizations, authz, servers, websites, jobs, audit logs, Inertia dashboard.
2. **Agent API** (`/api/agent/v1`): registration, heartbeat, discovery, job polling, metrics.
3. **Go Agent** (`agent/`): discovery, sandbox file ops, website enable/disable, service allowlist, metrics.

## Job lifecycle

1. User action in dashboard
2. Policy + permission check
3. `ServerJob` created (`pending`)
4. Agent polls `GET /api/agent/v1/jobs`
5. Agent validates command against local allowlist
6. Agent executes and posts result
7. UI reads job/result; audit log recorded

Laravel never SSHes into servers for normal operations.

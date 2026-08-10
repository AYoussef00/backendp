# Security

## Hard rules

- No `POST /execute` arbitrary command endpoint
- No `shell_exec($request->command)` patterns
- No unrestricted root SSH control path
- Agent allowlist is independent of Laravel compromise assumptions

## AuthN / AuthZ

- Users: Fortify session auth + organization team permissions (Spatie)
- Agents: hashed installation tokens (one-time) → hashed agent secrets
- Policies enforce organization isolation for servers/websites

## File sandbox

Allowed roots: `/var/www`, `/home`, `/srv`, `/opt`

Blocked: `/`, `/etc/shadow`, `/etc/ssh`, `/root`, `/proc`, `/sys`, `/boot`

## Operational controls

- Rate limiting on agent API
- Job expiration
- Idempotency keys
- Website action conflict checks
- Audit logging with secret redaction
- Metrics/job retention cleanup commands

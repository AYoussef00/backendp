# Deployment

1. Deploy Laravel control plane behind HTTPS.
2. Set `APP_URL` to the public panel URL (used by install scripts).
3. Run queue worker and scheduler.
4. Prefer Redis for cache/queue in production (`CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`).
5. Build agent binaries and host them for the installer download path (or bake into image).
6. Rotate installation tokens frequently; they expire automatically.

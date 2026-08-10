# Development

## Useful commands

```bash
php artisan migrate
php artisan db:seed
php artisan test
php artisan server:health
php artisan zyrox:cleanup --all
npm run dev
cd agent && go test ./...
```

## Conventions

- Controllers stay thin; use Services/Actions
- Every server mutation goes through `ServerJob`
- Always authorize with Policies + Spatie permissions
- Prefer structured agent error codes

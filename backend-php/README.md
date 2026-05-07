# PHP Backend

This directory is the active PHP app server for the Ardent project.

## Setup

1. Create `backend-php/.env` from `.env.example`.
2. Start the app server:

```powershell
cd backend-php
.\start-backend.ps1
```

## Default local URLs

- Customer app: `http://localhost:5001/`
- Admin app: `http://localhost:5001/admin/`
- API: `http://localhost:5001/api/...`

## Notes

- The server hosts the customer app, admin app, API routes, and uploaded images from one PHP process.
- Database tables are created automatically on first request.
- Uploaded images are stored in `backend-php/storage/uploads`.
- The startup script enables `pdo_mysql` automatically when the local PHP install has no active `php.ini`.

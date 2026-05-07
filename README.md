# Ardent Hotel Guest Services Platform

Full-stack hotel guest services application for food ordering, room booking, and admin operations built with:

- `frontend/`: customer app source plus prebuilt static bundle
- `admin/`: admin app source plus prebuilt static bundle
- `backend-php/`: PHP REST API
- `MySQL`: relational database with auto-bootstrap on server start

## Features

- JWT authentication with hashed passwords
- Role-based access control for `user` and `admin`
- Secure admin-only menu, order, and room-booking management APIs
- Full food CRUD: create, list, view, update, delete, and stock updates
- Cart persistence with guest-cart sync after login
- Order placement with server-calculated totals
- Room availability search with stay-date and guest filters
- Guest room booking management with admin status updates
- Cash on delivery support
- Optional Stripe card checkout if `STRIPE_SECRET_KEY` is configured
- Responsive customer storefront and protected admin dashboard

## Tech Stack

- React 18 + Vite
- PHP 8 + PDO
- MySQL
- JWT-compatible auth + bcrypt-compatible password hashing
- Stripe Checkout

## Environment Setup

Create these files from the examples:

- `backend-php/.env` from `backend-php/.env.example`

Important backend values:

- `DB_*` for your MySQL connection
- `JWT_SECRET` for signed auth tokens
- `ADMIN_EMAIL` and `ADMIN_PASSWORD` to bootstrap the first admin account
- `STRIPE_SECRET_KEY` only if you want card payments enabled

## MySQL

The backend creates missing tables automatically at startup.

If you want a manual schema reference, use [backend-php/database/schema.sql](backend-php/database/schema.sql).

Default database name expected by the app:

- `food_delivery_app`

## Running Locally

1. Start the PHP app server:

```powershell
cd backend-php
.\start-backend.ps1
```

## Default Local URLs

- Customer app: `http://localhost:5001/`
- Admin app: `http://localhost:5001/admin/`
- Backend API: `http://localhost:5001`

## Admin Login

Set these in `backend-php/.env` before starting the API:

```env
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=ChangeMe123
ADMIN_NAME=Platform Admin
```

On startup, the backend will create or promote that account to `admin`.

## Verification

- PHP backend files were syntax-checked with `php -l`.
- The prebuilt frontend and admin bundles are served directly by the PHP app server.

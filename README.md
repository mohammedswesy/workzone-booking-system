# WorkZone – Coworking Space Booking System (Laravel + Inertia + Vue)

WorkZone is a multi-role coworking space booking platform built with **Laravel**, **Inertia.js**, and **Vue**.
It supports **Admin**, **Owner**, and **User** dashboards with booking management and SaaS-ready structure.

## ✨ Key Features
- **Authentication & Roles** (Admin / Owner / User)
- **Workspaces Management** (create, update, availability, pricing)
- **Booking System**
  - booking requests
  - booking status (pending / approved / rejected / cancelled)
  - user booking history
- **Owner Dashboard**
  - manage workspaces
  - manage bookings
  - insights (basic)
- **Admin Dashboard**
  - manage users / owners
  - manage workspaces
  - moderation & system settings (basic)
- **Responsive UI** (Tailwind + Vue)

## 🧱 Tech Stack
- **Backend:** Laravel (PHP)
- **Frontend:** Inertia.js + Vue
- **Styling:** TailwindCSS
- **Database:** MySQL
- **Build:** Vite

## 🗂️ Project Structure (High Level)
- `app/` Laravel application logic
- `routes/` web routes
- `resources/js/` Vue + Inertia pages/components
- `database/` migrations + seeders

## 🚀 Getting Started (Local Setup)
> Requirements: PHP, Composer, Node.js, MySQL

```bash
git clone https://github.com/mohammedswesy/workzone-booking-system.git
cd workzone-booking-system

composer install
cp .env.example .env
php artisan key:generate

# configure your DB in .env then:
php artisan migrate --seed

npm install
npm run dev

php artisan serve

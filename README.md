# ⚡ Qloudflow Suite — Laravel WhatsApp Manager & CRM

This directory contains the **Laravel 12** web application for **Qloudflow Suite**, providing the full user interface, real-time dashboard, lead temperature classification, contact management, live chat inbox, and webhook listeners.

---

## 🚀 Demo Access & Credentials

- **URL**: `http://localhost:8000` (or `http://127.0.0.1:8000`)
- **Username**: `admin` (or `amarvcode@gmail.com`)
- **Password**: `password123`

---

## 🛠️ Setup & Local Development

```bash
# 1. Install dependencies
composer install
npm install

# 2. Setup Environment
cp .env.example .env
php artisan key:generate

# 3. Database Migration & Seeding
php artisan migrate:fresh --seed

# 4. Build Assets
npm run build

# 5. Serve Application
php artisan serve
```

---

## 📱 Features Included
- **Multi-Device Responsive UI**: Mobile drawer, swipeable filters, and touch action bars.
- **AI Knowledge Engine**: Automated responses for Qloudsoft Solutions services & packages.
- **Lead Classification**: Hot, Warm, Cold temperature tracking with dynamic scoring.
- **Interactive Bot Tester**: Floating simulator modal for testing AI responses on any screen.
- **WhatsApp Webhook Hub**: Real-time synchronization with the Baileys gateway on port 3001.

For root project documentation and architecture details, refer to the [Root README](../README.md).

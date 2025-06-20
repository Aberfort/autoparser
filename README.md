# SC AutoParser — Gemini Edition 📰🤖

> **Min WP:** 6.5 | **PHP:** 8.1+ | **Ліцензія:** GPL-2.0-or-later

SC AutoParser — це плагін для WordPress, який автоматично

1. **Парсить** статті з вказаних джерел (DomCrawler або PHPScraper).
2. **Рерайтить** текст через Google Gemini Flash-Lite, зберігаючи сенс і структуру.
3. **Публікує** контент у Gutenberg-редакторі (категорії, теги, ACF-поля, промо-блоки).
4. Працює за Cron або вручну — через WP-CLI чи React-адмінку.

---

## 🛠 Встановлення

### 1. ZIP-архів

Завантажте `sc-autoparser.zip` з GitHub Releases → «Плагіни → Додати → Завантажити».

---

## ⚡ Швидке налаштування

1. У меню **SC AutoParser** додайте URL-джерела.
2. У полі **Gemini API Key** збережіть ключ Google AI.
3. Натисніть **«Запустити парсинг»** 

---

## 🖥 CLI-команди

| Команда | Дія |
|---------|-----|
| `wp sc-autoparser run` | Запустити парсинг усіх джерел |
| `wp sc-autoparser publish --url=<url> [--delay=30] [--cat=1] [--tags=2,3]` | Опублікувати одну статтю або відкласти на *delay* хв |

---

## 👩‍💻 Розробка фронтенду

```bash
npm install
npm run build      # збирає React-бандл у assets/build/index.js
```
`npm run start` — watch-mode.

---

## 🧪 Lint

```bash
composer lint   # WordPress Coding Standards
```

## 🧪 API Key

https://ai.google.dev/ -> https://ai.google.dev/gemini-api/docs -> https://aistudio.google.com/u/1/apikey?hl=ru&pli=1

---

## API Key (Football API)

https://dashboard.api-football.com/soccer/tester
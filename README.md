# AutoParser — Gemini Edition 📰🤖

> **Min WP:** 6.5 | **PHP:** 8.2+ | **Ліцензія:** GPL-2.0-or-later

AutoParser — це плагін для WordPress, який автоматично

1. **Парсить** статті з вказаних джерел (Symfony DomCrawler).
2. **Рерайтить** текст через Google Gemini Flash-Lite, зберігаючи сенс і структуру.
3. **Публікує** контент у Gutenberg-редакторі (категорії, теги, ACF-поля, промо-блоки).
4. Працює за Cron або вручну — через WP-CLI чи React-адмінку.

---

## 🛠 Встановлення

### 1. ZIP-архів

Завантажте `autoparser.zip` з GitHub Releases → «Плагіни → Додати → Завантажити».

---

## ⚡ Швидке налаштування

1. У меню **AutoParser** додайте URL-джерела.
2. У полі **Gemini API Key** збережіть ключ Google AI.
3. Натисніть **«Запустити парсинг»** 

---

## 🖥 CLI-команди

| Команда | Дія |
|---------|-----|
| `wp autoparser run [--feed=<id>]` | Запустити парсинг усіх джерел або конкретного |
| `wp autoparser test-selector --url=<url> [--selector=<css>] [--selector-end=<css>]` | Перевірити, що витягне CSS-селектор, без збереження стрічки |

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

## 🧪 Тести

```bash
composer test   # PHPUnit — чиста логіка (UrlCanonicalizer, discover-cutoff), без БД/WordPress
```

## 🧪 API Key

https://ai.google.dev/ -> https://ai.google.dev/gemini-api/docs -> https://aistudio.google.com/u/1/apikey?hl=ru&pli=1

---

## API Key (Football API)

https://dashboard.api-football.com/soccer/tester
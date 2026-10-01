<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Telegram Service</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            font-family: "Segoe UI", sans-serif;
            background: #f3efe6;
            color: #1f2933;
        }
        .wrap { max-width: 720px; margin: 0 auto; padding: 40px 20px; }
        .card {
            background: #fffdf8;
            border: 1px solid #e5ded0;
            border-radius: 16px;
            padding: 24px;
        }
        h1 { margin: 0 0 8px; font-size: 28px; }
        p { color: #6b7280; line-height: 1.5; }
        a { color: #0f766e; }
        ul { padding-left: 18px; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Telegram Service</h1>
            <p>Бот, проверка кодов и кик участников без AD / с статусом «уволен».</p>
            <ul>
                <li><a href="http://localhost:8083" target="_blank" rel="noopener">phpMyAdmin telegram</a> — пользователь <code>telegram</code> / <code>telegram</code>, база <code>telegram_service</code></li>
                <li><a href="http://localhost:8082" target="_blank" rel="noopener">phpMyAdmin auth</a> — пользователь <code>auth</code> / <code>auth</code>, база <code>auth_service</code></li>
                <li><a href="http://localhost:8080">Виджет кодов</a></li>
            </ul>
        </div>
    </div>
</body>
</html>

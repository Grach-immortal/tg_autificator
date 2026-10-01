<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Виджет кода аутентификации</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f3efe6;
            --card: #fffdf8;
            --ink: #1f2933;
            --muted: #6b7280;
            --line: #e5ded0;
            --accent: #0f766e;
            --accent-ink: #115e59;
            --danger: #b42318;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--ink);
        }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 32px 20px 64px; }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .top a { color: var(--accent-ink); }
        h1 { margin: 0 0 8px; font-size: 28px; }
        p.lead { margin: 0 0 28px; color: var(--muted); max-width: 720px; line-height: 1.5; }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px;
        }
        .card h2 { margin: 0 0 10px; font-size: 18px; }
        .hint { color: var(--muted); font-size: 14px; line-height: 1.5; margin: 0 0 14px; }
        pre {
            margin: 0;
            padding: 14px;
            background: #1f2933;
            color: #f8fafc;
            border-radius: 10px;
            overflow: auto;
            font-size: 13px;
            line-height: 1.45;
        }
        .actions { display: flex; gap: 8px; margin-top: 12px; }
        button, .btn {
            appearance: none;
            border: 0;
            background: var(--accent);
            color: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font: inherit;
            cursor: pointer;
            text-decoration: none;
        }
        button.ghost, .btn.ghost {
            background: #fff;
            color: var(--accent-ink);
            border: 1px solid var(--line);
        }
        .picker { position: relative; max-width: 360px; margin: 0 0 20px; }
        .picker label { display: block; margin-bottom: 6px; font-size: 13px; color: var(--muted); }
        .picker-field { position: relative; }
        .picker input {
            width: 100%;
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 10px;
            padding: 10px 40px 10px 12px;
            font: inherit;
            color: var(--ink);
        }
        .picker input:focus { outline: 2px solid #99f6e4; border-color: #5eead4; }
        .picker-toggle {
            position: absolute;
            top: 4px;
            right: 4px;
            bottom: 4px;
            width: 32px;
            padding: 0;
            background: transparent;
            color: var(--muted);
            border-radius: 8px;
        }
        .picker-list {
            position: absolute;
            z-index: 5;
            left: 0;
            right: 0;
            margin: 4px 0 0;
            padding: 4px;
            list-style: none;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            max-height: 240px;
            overflow: auto;
            box-shadow: 0 8px 24px rgba(31, 41, 51, 0.08);
        }
        .picker-list[hidden] { display: none; }
        .picker-list a {
            display: block;
            padding: 8px 10px;
            border-radius: 8px;
            color: var(--ink);
            text-decoration: none;
            font-size: 14px;
        }
        .picker-list a:hover,
        .picker-list a.active,
        .picker-list a[aria-selected="true"] {
            background: #ccfbf1;
            color: var(--accent-ink);
        }
        .picker-empty { padding: 8px 10px; color: var(--muted); font-size: 14px; }
        .frame {
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .frame-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f8f4ec;
            border-bottom: 1px solid var(--line);
            font-size: 12px;
            color: var(--muted);
            word-break: break-all;
        }
        .demo-widget {
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #faf8f3;
        }
        .error {
            background: #fef3f2;
            color: var(--danger);
            border: 1px solid #fecdca;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 16px;
        }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="top">
            <strong>Auth Service</strong>
            <a href="http://localhost:8082" target="_blank" rel="noopener">phpMyAdmin</a>
        </div>
        <h1>Код для вставки</h1>
        <p class="lead">
            Отдайте сниппет любой странице. На той странице в параметре <code>id_ad</code>
            должен быть подписанный идентификатор сотрудника. Виджет запросит 6-значный код
            и раз в {{ $lifetime_seconds }} сек. обновится.
        </p>

        @if (!empty($error))
            <div class="error">{{ $error }}</div>
        @endif

        @if ($employees->isNotEmpty())
            <div class="picker" id="employee-picker">
                <label for="employee-search">Сотрудник</label>
                <div class="picker-field">
                    <input
                        id="employee-search"
                        type="text"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="employee-list"
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="Поиск"
                        value="{{ $current->id_ad ?? '' }}"
                    >
                    <button type="button" class="picker-toggle" id="employee-toggle" aria-label="Открыть список">▾</button>
                    <ul id="employee-list" class="picker-list" role="listbox" hidden>
                        @foreach ($employees as $employee)
                            <li>
                                <a
                                    role="option"
                                    href="{{ route('widget', ['id_ad' => $employee->id_ad]) }}"
                                    data-id="{{ $employee->id_ad }}"
                                    aria-selected="{{ $current && $current->id_ad === $employee->id_ad ? 'true' : 'false' }}"
                                >{{ $employee->id_ad }}</a>
                            </li>
                        @endforeach
                        <li id="employee-empty" class="picker-empty" hidden>Ничего не найдено</li>
                    </ul>
                </div>
            </div>
        @endif

        <div class="grid">
            <section class="card">
                <h2>Сниппет</h2>
                <p class="hint">
                    Один и тот же скрипт. Сотрудник определяется только параметром
                    <code>?id_ad=...</code> на странице встройки. Сырой логин AD не принимается:
                    значение должно быть HMAC-токеном.
                </p>
                <pre id="snippet">&lt;script src="{{ url('/widget.js') }}"&gt;&lt;/script&gt;</pre>
                <div class="actions">
                    <button type="button" id="copy-snippet">Скопировать сниппет</button>
                </div>

                <h2 style="margin-top: 24px;">Как передать id_ad</h2>
                <p class="hint">
                    После SSO на своей странице подпишите <code>id_ad</code> тем же
                    <code>WIDGET_TOKEN_SECRET</code> и откройте страницу с виджетом:
                </p>
                <pre>https://intranet.company/page?id_ad=ПОДПИСАННЫЙ_ТОКЕН</pre>
            </section>

            <section class="card">
                <h2>Демо вставленного кода</h2>
                <p class="hint">
                    Ниже обычная страница, на которую вставлен тот же сниппет.
                    В адресе iframe — подписанный <code>id_ad</code>
                    @if ($current)
                        для <code>{{ $current->id_ad }}</code>
                    @endif.
                </p>
                <div class="frame">
                    <div class="frame-bar">
                        {{ $token ? url('/').'?id_ad='.$token : 'Нет валидного id_ad' }}
                    </div>
                    <div class="demo-widget">
                        @if ($token)
                            <script src="/widget.js"></script>
                        @else
                            <div style="color: var(--muted);">
                                Нет тестовых записей в <code>auth_codes</code>. Запустите сидер.
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
    <script>
        document.getElementById('copy-snippet')?.addEventListener('click', async function () {
            const text = document.getElementById('snippet').textContent;
            await navigator.clipboard.writeText(text);
            this.textContent = 'Скопировано';
            setTimeout(() => { this.textContent = 'Скопировать сниппет'; }, 1600);
        });

        (function () {
            var root = document.getElementById('employee-picker');
            if (!root) return;

            var input = document.getElementById('employee-search');
            var list = document.getElementById('employee-list');
            var toggle = document.getElementById('employee-toggle');
            var empty = document.getElementById('employee-empty');
            var options = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
            var selected = input.value;

            function visibleOptions() {
                return options.filter(function (option) { return !option.parentElement.hidden; });
            }

            function setOpen(next) {
                list.hidden = !next;
                input.setAttribute('aria-expanded', next ? 'true' : 'false');
            }

            function highlight(option) {
                options.forEach(function (item) { item.classList.remove('active'); });
                if (!option) return;
                option.classList.add('active');
                option.scrollIntoView({ block: 'nearest' });
            }

            function filter(query) {
                var needle = query.trim().toLowerCase();
                var count = 0;
                options.forEach(function (option) {
                    var match = option.dataset.id.toLowerCase().indexOf(needle) !== -1;
                    option.parentElement.hidden = !match;
                    if (match) count += 1;
                });
                empty.hidden = count !== 0;
                highlight(null);
            }

            function openAll() {
                filter('');
                setOpen(true);
            }

            input.addEventListener('focus', function () {
                input.select();
                openAll();
            });

            input.addEventListener('input', function () {
                filter(input.value);
                setOpen(true);
            });

            input.addEventListener('keydown', function (event) {
                var visible = visibleOptions();
                var current = visible.findIndex(function (option) { return option.classList.contains('active'); });

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    setOpen(true);
                    highlight(visible[current < 0 ? 0 : Math.min(current + 1, visible.length - 1)]);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    highlight(visible[Math.max(current - 1, 0)]);
                } else if (event.key === 'Enter' && !list.hidden) {
                    var chosen = visible.find(function (option) { return option.classList.contains('active'); }) || visible[0];
                    if (chosen) {
                        event.preventDefault();
                        window.location.href = chosen.getAttribute('href');
                    }
                } else if (event.key === 'Escape') {
                    input.value = selected;
                    filter('');
                    setOpen(false);
                    input.blur();
                }
            });

            toggle.addEventListener('click', function () {
                if (list.hidden) {
                    input.focus();
                    openAll();
                } else {
                    input.value = selected;
                    filter('');
                    setOpen(false);
                }
            });

            document.addEventListener('mousedown', function (event) {
                if (root.contains(event.target)) return;
                input.value = selected;
                filter('');
                setOpen(false);
            });
        })();
    </script>
</body>
</html>

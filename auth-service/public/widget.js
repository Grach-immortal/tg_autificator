(function () {
    var script = document.currentScript || document.querySelector('script[src*="widget.js"]');
    if (!script || !script.src) {
        return;
    }

    var origin = new URL(script.src, window.location.href).origin;
    var token = new URLSearchParams(window.location.search).get('id_ad') || '';
    var root = document.createElement('div');

    root.className = 'tg-auth-widget';
    script.parentNode.insertBefore(root, script.nextSibling);
    injectStyles();

    if (!token) {
        renderError('В адресе страницы нет параметра id_ad.');
        return;
    }

    var timer = null;
    var loading = false;

    loadCode();

    function loadCode() {
        if (loading) {
            return;
        }

        loading = true;
        renderLoading();

        fetch(origin + '/api/widget/code?id_ad=' + encodeURIComponent(token), {
            headers: { Accept: 'application/json' },
        })
            .then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok) {
                        throw new Error(body.message || 'Не удалось получить код.');
                    }
                    return body;
                });
            })
            .then(function (payload) {
                loading = false;
                renderCode(payload);
                startPreloader(payload);
            })
            .catch(function (error) {
                loading = false;
                renderError(error.message || 'Не удалось получить код.');
            });
    }

    function startPreloader(payload) {
        var remaining = Math.max(1, Number(payload.lifetime) || 1) * 1000;
        var lifetime = Math.max(remaining, (Number(payload.refresh_seconds) || 0) * 1000);
        var endsAt = Date.parse(payload.expires_at);
        if (!endsAt || endsAt <= Date.now()) {
            endsAt = Date.now() + remaining;
        }

        var lastShown = -1;

        if (timer) {
            cancelAnimationFrame(timer);
        }

        function tick() {
            var leftMs = Math.max(0, endsAt - Date.now());
            var leftSec = Math.ceil(leftMs / 1000);
            var bar = root.querySelector('[data-bar]');
            var seconds = root.querySelector('[data-seconds]');

            if (bar) {
                bar.style.width = ((leftMs / lifetime) * 100) + '%';
            }

            if (seconds && leftSec !== lastShown) {
                lastShown = leftSec;
                seconds.textContent = formatTime(leftSec);
            }

            if (leftMs <= 0) {
                loadCode();
                return;
            }

            timer = requestAnimationFrame(tick);
        }

        timer = requestAnimationFrame(tick);
    }

    function renderLoading() {
        root.innerHTML =
            '<div class="tg-auth-card">' +
                '<div class="tg-auth-label">Код для Telegram</div>' +
                '<div class="tg-auth-code tg-auth-wait">······</div>' +
                '<div class="tg-auth-meta">Запрашиваю код…</div>' +
                '<div class="tg-auth-track"><div class="tg-auth-bar tg-auth-bar-busy"></div></div>' +
            '</div>';
    }

    function renderCode(payload) {
        root.innerHTML =
            '<div class="tg-auth-card">' +
                '<div class="tg-auth-label">Код для Telegram</div>' +
                '<div class="tg-auth-code">' + splitCode(payload.code) + '</div>' +
                '<div class="tg-auth-meta">Обновится через <span data-seconds>03:00</span></div>' +
                '<div class="tg-auth-track"><div class="tg-auth-bar" data-bar></div></div>' +
            '</div>';
    }

    function renderError(message) {
        root.innerHTML =
            '<div class="tg-auth-card tg-auth-error">' +
                '<div class="tg-auth-label">Виджет недоступен</div>' +
                '<div class="tg-auth-meta">' + escapeHtml(message) + '</div>' +
            '</div>';
    }

    function splitCode(code) {
        var digits = String(code || '').replace(/\D/g, '').padStart(6, '0').slice(0, 6);
        return escapeHtml(digits.slice(0, 3) + ' ' + digits.slice(3));
    }

    function formatTime(total) {
        var minutes = Math.floor(total / 60);
        var seconds = total % 60;
        return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function injectStyles() {
        if (document.getElementById('tg-auth-widget-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'tg-auth-widget-style';
        style.textContent =
            '.tg-auth-widget{font-family:"Segoe UI",sans-serif;color:#1f2933;width:min(360px,100%)}' +
            '.tg-auth-card{background:#fffdf8;border:1px solid #e5ded0;border-radius:16px;padding:18px 18px 14px;box-shadow:0 8px 24px rgba(31,41,51,.06)}' +
            '.tg-auth-label{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin-bottom:8px}' +
            '.tg-auth-code{font-size:40px;line-height:1;font-weight:700;letter-spacing:.12em;margin:6px 0 10px}' +
            '.tg-auth-wait{color:#9ca3af;letter-spacing:.28em}' +
            '.tg-auth-meta{font-size:13px;color:#6b7280;margin-bottom:12px}' +
            '.tg-auth-track{height:6px;background:#efe8db;border-radius:999px;overflow:hidden}' +
            '.tg-auth-bar{height:100%;width:100%;background:#0f766e;border-radius:inherit}' +
            '.tg-auth-bar-busy{width:40%;animation:tg-auth-load 1.1s ease-in-out infinite}' +
            '.tg-auth-error{border-color:#fecdca}' +
            '@keyframes tg-auth-load{0%{transform:translateX(-120%)}100%{transform:translateX(280%)}}';
        document.head.appendChild(style);
    }
})();

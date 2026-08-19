/* KULAGER — интерактив главной страницы: темы, боковое меню, панель призыва,
   анимация числовых показателей. Без зависимостей, работает без сборки. */
(function () {
  'use strict';

  var STORAGE_KEY = 'kulager-theme';
  var root = document.documentElement;

  /* ------------------------------------------------------------- темы */

  var themes = {};
  var themesNode = document.getElementById('kulager-themes');
  if (themesNode) {
    try { themes = JSON.parse(themesNode.textContent) || {}; } catch (e) { themes = {}; }
  }

  function readStored() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }

  function applyTheme(id, persist) {
    var theme = themes[id];
    if (!theme) return;

    Object.keys(theme.vars).forEach(function (name) {
      root.style.setProperty(name, theme.vars[name]);
    });
    root.setAttribute('data-theme', id);

    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta && theme.vars['--bg']) meta.setAttribute('content', theme.vars['--bg']);

    document.querySelectorAll('[data-theme-pick]').forEach(function (btn) {
      btn.setAttribute('aria-pressed', btn.getAttribute('data-theme-pick') === id ? 'true' : 'false');
    });

    if (persist) {
      try { localStorage.setItem(STORAGE_KEY, id); } catch (e) { /* приватный режим */ }
    }
  }

  // Тема из системных настроек — пока пользователь не выбрал свою
  var stored = readStored();
  var systemLight = false;
  try {
    systemLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
  } catch (e) { /* старые браузеры */ }

  applyTheme(stored && themes[stored] ? stored : (systemLight ? 'light' : 'dark'), false);

  try {
    var mq = window.matchMedia('(prefers-color-scheme: light)');
    var onScheme = function (event) {
      if (readStored()) return;
      applyTheme(event.matches ? 'light' : 'dark', false);
    };
    if (mq.addEventListener) mq.addEventListener('change', onScheme);
    else if (mq.addListener) mq.addListener(onScheme);
  } catch (e) { /* не критично */ }

  document.querySelectorAll('[data-theme-pick]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      applyTheme(btn.getAttribute('data-theme-pick'), true);
    });
  });

  /* ------------------------------------------------------- боковое меню */

  var drawer = document.querySelector('[data-drawer]');
  var backdrop = document.querySelector('[data-backdrop]');
  var burger = document.querySelector('[data-burger]');

  function setDrawer(open) {
    if (!drawer) return;
    drawer.classList.toggle('is-open', open);
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (backdrop) backdrop.classList.toggle('is-open', open);
    if (burger) burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.classList.toggle('is-locked', open);
  }

  setDrawer(false);

  if (burger) {
    burger.addEventListener('click', function () {
      setDrawer(!drawer.classList.contains('is-open'));
    });
  }

  if (backdrop) backdrop.addEventListener('click', function () { setDrawer(false); });

  document.querySelectorAll('[data-drawer-close]').forEach(function (el) {
    el.addEventListener('click', function () { setDrawer(false); });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') setDrawer(false);
  });

  /* ------------------------------- галерея снимков на страницах товара */

  document.querySelectorAll('[data-gallery]').forEach(function (gallery) {
    var stage = gallery.querySelector('[data-gallery-stage]');
    var thumbs = gallery.querySelectorAll('[data-gallery-thumb]');
    if (!stage || !thumbs.length) return;

    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        stage.src = thumb.getAttribute('data-gallery-thumb');
        thumbs.forEach(function (other) {
          other.setAttribute('aria-current', other === thumb ? 'true' : 'false');
        });
      });
    });
  });

  /* --------------------------------- панель призыва и счётчики показателей */

  var bar = document.querySelector('[data-bar]');

  function runCount(el) {
    var full = el.textContent;
    var numbers = full.match(/\d+/g);
    if (!numbers || !numbers.length) return;

    var targets = numbers.map(Number);
    var duration = 700;
    var start = performance.now();

    function step(now) {
      var progress = Math.min(1, (now - start) / duration);
      var eased = 1 - Math.pow(1 - progress, 3);
      var i = 0;

      el.textContent = full.replace(/\d+/g, function () {
        return String(Math.round(targets[i++] * eased));
      });

      if (progress < 1) requestAnimationFrame(step);
      else el.textContent = full;
    }

    el.textContent = full.replace(/\d+/g, function () { return '0'; });
    requestAnimationFrame(step);
  }

  function sweep() {
    var viewport = window.innerHeight || root.clientHeight;

    if (bar) bar.classList.toggle('is-visible', window.scrollY > viewport * 0.75);

    document.querySelectorAll('[data-count]:not([data-count-done])').forEach(function (el) {
      var box = el.getBoundingClientRect();
      if (box.top < viewport * 0.85 && box.bottom > 0) {
        el.setAttribute('data-count-done', '');
        runCount(el);
      }
    });
  }

  window.addEventListener('scroll', sweep, { passive: true });
  window.addEventListener('resize', sweep, { passive: true });
  sweep();
})();

/* ------------------------------------------------------------- форма заявки */

/**
 * Отправка без перезагрузки. Если скрипт не сработает, форма уйдёт обычным
 * POST и вернётся на страницу с сообщением — поведение не потеряется.
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-lead-form]').forEach(function (form) {
    // Отмечаем момент, когда форма реально показалась посетителю
    var started = form.querySelector('[data-form-started]');
    if (started) started.value = String(Math.floor(Date.now() / 1000));

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var button = form.querySelector('button[type="submit"]');
      var note = form.parentNode.querySelector('[data-form-note]');

      if (!note) {
        note = document.createElement('p');
        note.className = 'form-note';
        note.setAttribute('data-form-note', '');
        form.parentNode.insertBefore(note, form);
      }

      if (button) button.disabled = true;
      note.className = 'form-note';
      note.textContent = 'Отправляем…';

      fetch(form.getAttribute('action'), {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' },
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (button) button.disabled = false;

          if (data && data.ok) {
            note.className = 'form-note form-note--ok';
            note.textContent = form.getAttribute('data-success')
              || 'Заявка отправлена. Мы свяжемся с вами в рабочее время.';
            form.reset();

            return;
          }

          note.className = 'form-note form-note--error';
          note.textContent = (data && data.error) || 'Не удалось отправить. Попробуйте ещё раз.';
        })
        .catch(function () {
          if (button) button.disabled = false;
          note.className = 'form-note form-note--error';
          note.textContent = 'Нет связи с сервером. Напишите нам в WhatsApp.';
        });
    });
  });
})();

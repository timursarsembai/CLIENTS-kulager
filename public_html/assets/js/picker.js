/**
 * Окно выбора картинки: библиотека медиафайлов и загрузка.
 *
 * Один и тот же файл подключают админка и страница сайта в режиме правки.
 * Наружу отдаётся window.KulagerPicker.open(onChoose) — вызывающий сам решает,
 * что делать с выбранным путём: подставить в поле формы или в <img> на странице.
 */
window.KulagerPicker = (function () {
  'use strict';

  var picker = document.querySelector('[data-picker]');
  var onChoose = null;
  var loaded = false;

  /** Куда писать короткие сообщения — у админки и сайта они разные. */
  function say(text, isError) {
    if (typeof window.KULAGER_FLASH === 'function') {
      window.KULAGER_FLASH(text, isError);
    }
  }

  function open(handler) {
    if (!picker) return;

    onChoose = handler;
    picker.hidden = false;
    document.body.classList.add('is-locked');
    showTab('library');

    if (!loaded) loadLibrary();
  }

  function close() {
    if (!picker) return;

    picker.hidden = true;
    onChoose = null;
    document.body.classList.remove('is-locked');
  }

  function showTab(name) {
    picker.querySelectorAll('[data-picker-tab]').forEach(function (tab) {
      tab.classList.toggle('is-active', tab.getAttribute('data-picker-tab') === name);
    });

    picker.querySelectorAll('[data-picker-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-picker-panel') !== name;
    });
  }

  function choose(path) {
    if (typeof onChoose === 'function') {
      onChoose(path);
      say('Картинка выбрана');
    }

    close();
  }

  function loadLibrary() {
    var grid = picker.querySelector('[data-picker-grid]');
    var status = picker.querySelector('[data-picker-status]');

    fetch('/admin/media/json', { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        var items = (data && data.items) || [];
        loaded = true;
        grid.innerHTML = '';

        if (items.length === 0) {
          status.hidden = false;
          status.textContent = 'В библиотеке пока пусто — загрузите файл на соседней вкладке.';
          return;
        }

        status.hidden = true;
        items.forEach(function (item) { grid.appendChild(card(item)); });
      })
      .catch(function () {
        status.hidden = false;
        status.textContent = 'Не удалось загрузить библиотеку.';
      });
  }

  function card(item) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'picker__item';
    button.setAttribute('data-picker-choose', item.path);

    var image = document.createElement('img');
    image.src = '/assets/' + item.path;
    image.alt = item.alt || '';
    image.loading = 'lazy';

    var caption = document.createElement('span');
    caption.className = 'picker__item-name';
    caption.textContent = item.name;

    button.appendChild(image);
    button.appendChild(caption);

    return button;
  }

  function upload(files) {
    if (!files || files.length === 0) return;

    var status = picker.querySelector('[data-picker-upload-status]');
    var body = new FormData();

    body.append('_token', window.KULAGER_TOKEN || window.KULAGER_EDIT_TOKEN || '');
    body.append('json', '1');

    Array.prototype.forEach.call(files, function (file) { body.append('files[]', file); });

    status.hidden = false;
    status.textContent = 'Загружаем…';

    fetch('/admin/media/upload', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        var uploaded = (data && data.uploaded) || [];
        var errors = (data && data.errors) || [];

        if (errors.length > 0) {
          status.textContent = errors.join('; ');
        } else {
          status.hidden = true;
        }

        if (uploaded.length === 0) return;

        // Библиотеку перечитываем, чтобы новый файл был на месте и дальше
        loaded = false;
        loadLibrary();
        choose(uploaded[0].path);
      })
      .catch(function () {
        status.hidden = false;
        status.textContent = 'Не удалось загрузить файл.';
      });
  }

  if (picker) {
    picker.addEventListener('click', function (event) {
      if (event.target.closest('[data-picker-close]')) {
        close();
        return;
      }

      var tab = event.target.closest('[data-picker-tab]');
      if (tab) {
        showTab(tab.getAttribute('data-picker-tab'));
        return;
      }

      var item = event.target.closest('[data-picker-choose]');
      if (item) choose(item.getAttribute('data-picker-choose'));
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !picker.hidden) close();
    });

    var file = picker.querySelector('[data-picker-file]');
    var drop = picker.querySelector('[data-picker-drop]');

    if (file) {
      file.addEventListener('change', function () {
        upload(file.files);
        file.value = '';
      });
    }

    if (drop) {
      ['dragenter', 'dragover'].forEach(function (name) {
        drop.addEventListener(name, function (event) {
          event.preventDefault();
          drop.classList.add('is-over');
        });
      });

      ['dragleave', 'drop'].forEach(function (name) {
        drop.addEventListener(name, function (event) {
          event.preventDefault();
          drop.classList.remove('is-over');
        });
      });

      drop.addEventListener('drop', function (event) {
        upload(event.dataTransfer && event.dataTransfer.files);
      });
    }
  }

  return { open: open, close: close, available: !!picker };
})();

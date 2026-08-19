/**
 * Правка текста прямо на странице.
 *
 * Загружается только в режиме правки — обычный посетитель этот файл
 * не получает. Каждое помеченное место (data-edit-block + data-edit-path)
 * становится редактируемым; после ухода фокуса значение уходит на сервер,
 * где проходит ту же проверку, что и форма в админке.
 */
(function () {
  'use strict';

  var fields = document.querySelectorAll('[data-edit-path]');

  if (fields.length === 0) return;

  var status = document.querySelector('[data-edit-status]');
  var timer = null;

  function say(text, isError) {
    if (!status) return;

    status.textContent = text;
    status.classList.toggle('is-error', !!isError);

    clearTimeout(timer);
    timer = setTimeout(function () { status.textContent = ''; }, 2500);
  }

  /** Значение поля: с разметкой или простым текстом — как помечено в шаблоне. */
  function readValue(field) {
    return field.hasAttribute('data-edit-html')
      ? field.innerHTML.trim()
      : field.innerText.replace(/\u00a0/g, ' ').trim();
  }

  function writeValue(field, value) {
    if (field.hasAttribute('data-edit-html')) {
      field.innerHTML = value;
    } else {
      field.innerText = value;
    }
  }

  function save(field) {
    var value = readValue(field);

    if (value === field.dataset.editWas) return;

    var body = new FormData();
    body.append('_token', window.KULAGER_EDIT_TOKEN || '');
    body.append('path', field.getAttribute('data-edit-path'));
    body.append('value', value);

    // Одно и то же поле правки, но источников четыре: блок страницы,
    // пункт меню, строка интерфейса и поле самой страницы
    if (field.hasAttribute('data-edit-nav')) {
      body.append('nav', field.getAttribute('data-edit-nav'));
    } else if (field.hasAttribute('data-edit-text')) {
      body.append('text', field.getAttribute('data-edit-text'));
    } else if (field.hasAttribute('data-edit-setting')) {
      body.append('setting', field.getAttribute('data-edit-setting'));
    } else if (field.hasAttribute('data-edit-page')) {
      body.append('page', field.getAttribute('data-edit-page'));
    } else {
      body.append('block', field.getAttribute('data-edit-block'));
    }

    field.classList.add('is-saving');
    say('Сохраняем…');

    fetch('/admin/inline', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
      .then(function (result) {
        field.classList.remove('is-saving');

        if (!result.ok || result.data.error) {
          // Не сохранилось — возвращаем прежний текст, чтобы на экране
          // не осталось то, чего нет в базе
          writeValue(field, field.dataset.editWas);
          say(result.data.error || 'Не сохранилось', true);

          return;
        }

        field.dataset.editWas = value;
        say('Сохранено');
      })
      .catch(function () {
        field.classList.remove('is-saving');
        writeValue(field, field.dataset.editWas);
        say('Нет связи с сервером', true);
      });
  }

  Array.prototype.forEach.call(fields, function (field) {
    var isHtml = field.hasAttribute('data-edit-html');

    field.dataset.editWas = readValue(field);
    field.setAttribute('contenteditable', isHtml ? 'true' : 'plaintext-only');
    field.classList.add('is-editable');

    field.addEventListener('blur', function () { save(field); });

    field.addEventListener('keydown', function (event) {
      // В обычных полях переносов строк не бывает: Enter завершает правку.
      // В полях с разметкой Enter нужен — там перенос строки осмыслен
      if (event.key === 'Enter' && !isHtml) {
        event.preventDefault();
        field.blur();
      }

      if (event.key === 'Escape') {
        writeValue(field, field.dataset.editWas);
        field.blur();
      }
    });

    // Вставка только текстом: из Word прилетает разметка, которой тут не место
    field.addEventListener('paste', function (event) {
      event.preventDefault();

      var text = (event.clipboardData || window.clipboardData).getData('text');
      document.execCommand('insertText', false, text.replace(/\s+/g, ' '));
    });

    /*
     * Клик по редактируемому тексту — это начало правки, а не переход.
     * Останавливаем и переход по ссылке, и всплытие: в боковом меню
     * на тех же элементах висит закрытие меню.
     */
    field.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
    }, true);
  });

  // Карточка целиком бывает ссылкой — в режиме правки это мешает
  document.querySelectorAll('a.cell--link').forEach(function (card) {
    card.addEventListener('click', function (event) {
      if (event.target.closest('[data-edit-path]')) event.preventDefault();
    });
  });
})();

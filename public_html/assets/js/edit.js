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

  // Окно выбора картинки показывает свои сообщения через ту же строку в панели
  window.KULAGER_FLASH = function (text, isError) { say(text, isError); };

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

  /* ------------------------------------------------------------ картинки */

  /**
   * Замена картинки прямо на странице.
   *
   * Кнопку не вставляем рядом с каждым <img>: обложка растянута на весь экран,
   * карточки лежат в сетке — любая вставка в разметку поехала бы. Вместо этого
   * одна плавающая кнопка переставляется к той картинке, над которой курсор.
   */
  var images = document.querySelectorAll('[data-edit-image]');

  if (images.length > 0 && window.KulagerPicker && window.KulagerPicker.available) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'edit-image-btn';
    button.textContent = 'Заменить картинку';
    button.hidden = true;
    document.body.appendChild(button);

    var target = null;

    var place = function (image) {
      var box = image.getBoundingClientRect();

      // Ушла из виду — прячем кнопку вместе с ней
      if (box.bottom < 0 || box.top > window.innerHeight) {
        hide();
        return;
      }

      if (target && target !== image) target.classList.remove('is-hovered');

      target = image;
      image.classList.add('is-hovered');
      button.hidden = false;
      button.style.top = Math.max(8, box.top + 12) + 'px';
      button.style.left = Math.min(window.innerWidth - 16, box.right - 12) + 'px';
    };

    var hide = function () {
      if (target) target.classList.remove('is-hovered');

      button.hidden = true;
      target = null;
    };

    var replace = function (image) {
      window.KulagerPicker.open(function (path) {
        var body = new FormData();
        body.append('_token', window.KULAGER_EDIT_TOKEN || '');
        body.append('block', image.getAttribute('data-edit-block'));
        body.append('path', image.getAttribute('data-edit-image'));
        body.append('value', path);

        say('Сохраняем…');

        fetch('/admin/inline', { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data || data.error) {
              say((data && data.error) || 'Не сохранилось', true);
              return;
            }

            // Показываем новую картинку сразу, не перезагружая страницу
            image.src = '/assets/' + path;
            say('Картинка заменена');
          })
          .catch(function () { say('Нет связи с сервером', true); });
      });
    };

    Array.prototype.forEach.call(images, function (image) {
      image.classList.add('is-editable-image');

      image.addEventListener('click', function (event) {
        event.preventDefault();
        replace(image);
      });
    });

    button.addEventListener('click', function () {
      if (target) replace(target);
    });

    /**
     * Картинку ищем по координатам курсора, а не по наведению на неё.
     *
     * В обложке поверх фотографии лежат затемнение и текстовый слой — они
     * перехватывают курсор, и mouseenter до картинки не доходит. Проверка
     * по прямоугольнику работает независимо от того, что сверху.
     */
    var pending = false;

    document.addEventListener('mousemove', function (event) {
      if (pending) return;

      pending = true;

      requestAnimationFrame(function () {
        pending = false;

        // Над самой кнопкой ничего не пересчитываем — иначе она убежит
        if (event.target.closest('.edit-image-btn')) return;

        // Над редактируемым текстом кнопка мешает: там правят текст
        if (event.target.closest('[data-edit-path]')) {
          hide();

          return;
        }

        var found = null;

        Array.prototype.forEach.call(images, function (image) {
          var box = image.getBoundingClientRect();

          if (box.width === 0 || box.height === 0) return;

          var inside = event.clientX >= box.left && event.clientX <= box.right
            && event.clientY >= box.top && event.clientY <= box.bottom;

          // Меньшая картинка важнее: миниатюра галереи лежит поверх крупной
          if (inside && (found === null || box.width < found.width)) {
            found = { image: image, width: box.width };
          }
        });

        if (found === null) {
          hide();

          return;
        }

        place(found.image);
      });
    }, { passive: true });

    window.addEventListener('scroll', function () {
      if (target) place(target);
    }, { passive: true });
  }

  // Карточка целиком бывает ссылкой — в режиме правки это мешает
  document.querySelectorAll('a.cell--link').forEach(function (card) {
    card.addEventListener('click', function (event) {
      if (event.target.closest('[data-edit-path]')) event.preventDefault();
    });
  });
})();

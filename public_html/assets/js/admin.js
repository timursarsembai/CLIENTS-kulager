/* Админка KULAGER: перетаскивание блоков, повторяющиеся группы полей,
   поле с оформлением текста. Без зависимостей и без сборки. */
(function () {
  'use strict';

  /* ------------------------------------------------- разделы на телефоне */

  var topbar = document.querySelector('[data-topbar]');
  var topbarToggle = document.querySelector('[data-topbar-toggle]');
  var topbarScrim = document.querySelector('[data-topbar-scrim]');

  if (topbar && topbarToggle) {
    /* Панель выезжает поверх страницы, поэтому её открытость помечаем и на
       body: под ней страница не прокручивается, а затемнение проявляется. */
    var setNav = function (open) {
      topbar.classList.toggle('is-open', open);
      document.body.classList.toggle('is-nav-open', open);
      topbarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    topbarToggle.addEventListener('click', function () {
      setNav(!topbar.classList.contains('is-open'));
    });

    if (topbarScrim) {
      topbarScrim.addEventListener('click', function () { setNav(false); });
    }

    var topbarClose = topbar.querySelector('[data-topbar-close]');

    if (topbarClose) {
      topbarClose.addEventListener('click', function () {
        setNav(false);
        topbarToggle.focus();
      });
    }

    // Переход по разделу закрывает панель сам: страница успевает смениться
    topbar.querySelectorAll('.topbar__nav a').forEach(function (link) {
      link.addEventListener('click', function () { setNav(false); });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && topbar.classList.contains('is-open')) {
        setNav(false);
        topbarToggle.focus();
      }
    });

    // Экран растянули до настольной ширины — панель больше не нужна
    window.addEventListener('resize', function () {
      if (window.innerWidth > 760 && topbar.classList.contains('is-open')) {
        setNav(false);
      }
    });
  }

  /* ------------------------------------------- подтверждение перед удалением */

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  /* ---------------------------------------------- перетаскивание, общая часть */

  /**
   * Делает элементы списка перетаскиваемыми за ручку.
   * onDrop вызывается после того, как порядок изменился.
   */
  function makeSortable(container, itemSelector, handleSelector, onDrop) {
    if (!container) return;

    var dragged = null;

    container.addEventListener('pointerdown', function (event) {
      var handle = event.target.closest(handleSelector);
      if (!handle) return;

      var item = handle.closest(itemSelector);
      if (item) item.draggable = true;
    });

    container.addEventListener('dragstart', function (event) {
      dragged = event.target.closest(itemSelector);
      if (!dragged) return;

      dragged.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      // Safari не начинает перенос без данных
      event.dataTransfer.setData('text/plain', '');
    });

    container.addEventListener('dragover', function (event) {
      if (!dragged) return;
      event.preventDefault();

      var over = event.target.closest(itemSelector);
      if (!over || over === dragged) return;

      var box = over.getBoundingClientRect();
      var after = event.clientY > box.top + box.height / 2;

      container.insertBefore(dragged, after ? over.nextSibling : over);
    });

    container.addEventListener('dragend', function () {
      if (!dragged) return;

      dragged.classList.remove('is-dragging');
      dragged.draggable = false;
      dragged = null;

      if (onDrop) onDrop();
    });
  }

  /* ------------------------------------------------ порядок блоков страницы */

  var blocks = document.querySelector('[data-blocks]');

  if (blocks) {
    var saveTimer = null;

    var saveOrder = function () {
      clearTimeout(saveTimer);

      saveTimer = setTimeout(function () {
        var body = new FormData();
        body.append('_token', blocks.getAttribute('data-token'));

        blocks.querySelectorAll('[data-block-id]').forEach(function (item) {
          body.append('order[]', item.getAttribute('data-block-id'));
        });

        blocks.classList.add('is-saving');

        fetch(blocks.getAttribute('data-reorder-url'), {
          method: 'POST',
          body: body,
          credentials: 'same-origin',
        })
          .then(function (response) { return response.json(); })
          .then(function () {
            blocks.classList.remove('is-saving');
            flash('Порядок сохранён');
          })
          .catch(function () {
            blocks.classList.remove('is-saving');
            flash('Не удалось сохранить порядок', true);
          });
      }, 250);
    };

    makeSortable(blocks, '.block', '[data-block-handle]', saveOrder);
  }

  /* --------------------------------------------------------- порядок в меню */

  document.querySelectorAll('[data-menu-sortable]').forEach(function (list) {
    var url = list.getAttribute('data-menu-reorder');
    var timer = null;

    var saveOrder = function () {
      clearTimeout(timer);

      timer = setTimeout(function () {
        var body = new FormData();
        body.append('_token', window.KULAGER_TOKEN || '');

        // Порядок берём как есть по дереву: группы и их пункты идут подряд
        list.querySelectorAll('[data-menu-id]').forEach(function (item) {
          body.append('order[]', item.getAttribute('data-menu-id'));
        });

        fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (response) { return response.json(); })
          .then(function () { flash('Порядок сохранён'); })
          .catch(function () { flash('Не удалось сохранить порядок', true); });
      }, 250);
    };

    // Внутри групп пункты переставляются в своей колонке
    list.querySelectorAll('.menu-group__items').forEach(function (group) {
      makeSortable(group, '[data-menu-id]', '[data-list-handle]', saveOrder);
    });

    makeSortable(list, '[data-menu-id]', '[data-list-handle]', saveOrder);
  });

  /* ----------------------------------------------- повторяющиеся группы полей */

  document.querySelectorAll('[data-list]').forEach(function (list) {
    var items = list.querySelector('[data-list-items]');
    var template = list.querySelector('[data-list-template]');
    var addButton = list.querySelector('[data-list-add]');
    var max = parseInt(list.getAttribute('data-list-max') || '0', 10);

    if (!items || !template) return;

    var renumber = function () {
      // Имена полей содержат индекс: после перестановки его нужно пересчитать
      items.querySelectorAll('[data-list-item]').forEach(function (item, index) {
        item.querySelectorAll('[name]').forEach(function (input) {
          input.name = input.name.replace(/\[(\d+|__INDEX__)\]/, '[' + index + ']');
        });
      });

      if (max && addButton) {
        addButton.hidden = items.children.length >= max;
      }
    };

    var add = function () {
      var index = items.children.length;

      if (max && index >= max) return;

      var html = template.innerHTML.replace(/__INDEX__/g, String(index));
      var holder = document.createElement('div');
      holder.innerHTML = html;

      var node = holder.firstElementChild;
      items.appendChild(node);

      bindRichtext(node);
      bindImagePreview(node);
      bindPageFields(node);
      filterPages();
      renumber();

      var firstInput = node.querySelector('input, textarea, select');
      if (firstInput) firstInput.focus();
    };

    if (addButton) addButton.addEventListener('click', add);

    list.addEventListener('click', function (event) {
      var remove = event.target.closest('[data-list-remove]');
      if (!remove || !list.contains(remove)) return;

      var item = remove.closest('[data-list-item]');
      if (item) {
        item.remove();
        renumber();
      }
    });

    makeSortable(items, '[data-list-item]', '[data-list-handle]', renumber);
    renumber();
  });

  /* ---------------------------------------------------- ссылка на страницу сайта */

  /**
   * Поле выбора страницы: выпадающий список плюс пункт «Другой адрес…».
   * Имя поля переносится на тот элемент, который сейчас редактируют, —
   * так на сервер уходит ровно одно значение.
   */
  function bindPageFields(root) {
    (root || document).querySelectorAll('[data-page-field]').forEach(function (field) {
      if (field.dataset.bound) return;
      field.dataset.bound = '1';

      var select = field.querySelector('[data-page-select]');
      var input = field.querySelector('[data-page-input]');

      if (!select || !input) return;

      select.addEventListener('change', function () {
        var custom = select.value === '__custom__';
        var name = select.getAttribute('name') || input.getAttribute('name');

        if (custom) {
          select.removeAttribute('name');
          input.setAttribute('name', name);
          input.hidden = false;
          input.focus();
        } else {
          input.removeAttribute('name');
          input.hidden = true;
          input.value = '';
          select.setAttribute('name', name);
        }
      });
    });
  }

  /**
   * Раздел, выбранный в блоке, оставляет в списках только свои страницы.
   * Уже выбранную страницу не прячем: редактор мог поставить её осознанно
   * до смены раздела, и молча терять ссылку нельзя.
   */
  function filterPages() {
    var source = document.querySelector('[data-page-source]');

    if (!source) return;

    var section = source.value;

    document.querySelectorAll('[data-page-select]').forEach(function (select) {
      select.querySelectorAll('optgroup').forEach(function (group) {
        var options = group.querySelectorAll('option[data-section]');
        var match = !section || (options[0] && options[0].dataset.section === section);
        var shown = false;

        options.forEach(function (option) {
          var keep = match || option.selected;

          option.hidden = !keep;
          option.disabled = !keep;
          shown = shown || keep;
        });

        group.hidden = !shown;
      });
    });
  }

  bindPageFields(document);

  var pageSource = document.querySelector('[data-page-source]');
  if (pageSource) pageSource.addEventListener('change', filterPages);
  filterPages();

  /* ------------------------------------------------- поле с оформлением текста */

  function bindRichtext(root) {
    (root || document).querySelectorAll('[data-richtext]').forEach(function (field) {
      if (field.dataset.bound) return;
      field.dataset.bound = '1';

      var area = field.querySelector('[data-richtext-area]');
      var value = field.querySelector('[data-richtext-value]');
      if (!area || !value) return;

      var sync = function () { value.value = area.innerHTML.trim(); };

      area.addEventListener('input', sync);
      area.addEventListener('blur', sync);

      // Вставляем только текст: чужое форматирование из Word нам не нужно
      area.addEventListener('paste', function (event) {
        event.preventDefault();
        var text = (event.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
        sync();
      });

      field.querySelectorAll('[data-cmd]').forEach(function (button) {
        button.addEventListener('click', function () {
          var command = button.getAttribute('data-cmd');
          area.focus();

          if (command === 'createLink') {
            var url = window.prompt('Адрес ссылки', 'https://');
            if (url) document.execCommand('createLink', false, url);
          } else {
            document.execCommand(command, false, null);
          }

          sync();
        });
      });
    });
  }

  /* --------------------------------------------------- предпросмотр картинки */

  function bindImagePreview(root) {
    (root || document).querySelectorAll('[data-image-field]').forEach(function (field) {
      if (field.dataset.bound) return;
      field.dataset.bound = '1';

      var input = field.querySelector('[data-image-input]');
      var preview = field.querySelector('[data-image-preview]');
      var pick = field.querySelector('[data-image-pick]');
      if (!input || !preview) return;

      // Выбор из библиотеки: окно открывается поверх формы, ничего не перезагружая
      if (pick) {
        pick.addEventListener('click', function () {
          openPicker(input);
        });
      }

      var update = function () {
        var path = input.value.trim();

        if (path === '') {
          preview.hidden = true;
          return;
        }

        preview.src = '/assets/' + path.replace(/^\/+/, '');
        preview.hidden = false;
      };

      input.addEventListener('change', update);
      input.addEventListener('blur', update);
      preview.addEventListener('error', function () { preview.hidden = true; });
    });
  }

  /* ------------------------------------------------- адрес страницы из заголовка */

  var CYR_TO_LAT = {
    'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e',
    'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm',
    'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
    'ф': 'f', 'х': 'h', 'ц': 'c', 'ч': 'ch', 'ш': 'sh', 'щ': 'sch',
    'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya',
    'ә': 'a', 'ғ': 'g', 'қ': 'q', 'ң': 'n', 'ө': 'o', 'ұ': 'u',
    'ү': 'u', 'һ': 'h', 'і': 'i',
  };

  /** Те же правила, что в PHP-классе Slug: сервер всё равно пересчитает. */
  function slugify(text) {
    var out = '';

    text.toLowerCase().split('').forEach(function (char) {
      out += CYR_TO_LAT.hasOwnProperty(char) ? CYR_TO_LAT[char] : char;
    });

    return out.replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
  }

  var slugInput = document.querySelector('[data-slug-input]');
  var slugSource = document.querySelector('[data-slug-source]');

  if (slugInput && slugSource) {
    // Вложенность сохраняем: у отрасли адрес начинается с otrasli/
    var slugPrefix = function () {
      var value = slugInput.value;
      var cut = value.lastIndexOf('/');

      return cut === -1 ? '' : value.slice(0, cut + 1);
    };

    var fillSlug = function () {
      var made = slugify(slugSource.value);

      if (made !== '') slugInput.value = slugPrefix() + made;
    };

    var makeButton = document.querySelector('[data-slug-make]');
    if (makeButton) makeButton.addEventListener('click', fillSlug);

    // Пустой адрес заполняем по ходу набора заголовка: у новой страницы
    // его обычно и не заполняют вручную
    slugSource.addEventListener('input', function () {
      if (slugInput.value === '' || slugInput.dataset.auto === '1') {
        slugInput.dataset.auto = '1';
        slugInput.value = slugify(slugSource.value);
      }
    });

    // Как только адрес правят руками, автоподстановка прекращается
    slugInput.addEventListener('input', function () {
      slugInput.dataset.auto = '0';
    });
  }

  /* ------------------------------------------------------------ правка темы */

  var themeForm = document.querySelector('[data-theme-form]');

  if (themeForm) {
    var preview = themeForm.querySelector('[data-theme-preview]');

    /** Переносит значения полей в предпросмотр — он живёт на тех же переменных. */
    var applyTheme = function () {
      if (!preview) return;

      themeForm.querySelectorAll('[data-theme-value]').forEach(function (input) {
        preview.style.setProperty(input.getAttribute('data-var'), input.value);
      });
    };

    // Пипетка и текстовое поле — две записи одного значения
    themeForm.querySelectorAll('[data-theme-color]').forEach(function (picker) {
      var text = picker.parentNode.querySelector('[data-theme-value]');
      if (!text) return;

      picker.addEventListener('input', function () {
        text.value = picker.value;
        applyTheme();
      });

      text.addEventListener('input', function () {
        if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value;
        applyTheme();
      });
    });

    themeForm.querySelectorAll('[data-theme-value]').forEach(function (input) {
      input.addEventListener('input', applyTheme);
    });

    /*
     * Полупрозрачные слои: вместо ручного «rgba(8,19,31,0.98)» — пипетка
     * и ползунок. Значение собираем сами, редактору цифры видеть незачем.
     */
    themeForm.querySelectorAll('[data-theme-alpha]').forEach(function (holder) {
      var picker = holder.querySelector('[data-alpha-color]');
      var range = holder.querySelector('[data-alpha-range]');
      var out = holder.querySelector('[data-alpha-out]');
      var value = holder.querySelector('[data-theme-value]');

      if (!picker || !range || !value) return;

      var compose = function () {
        var hex = picker.value.replace('#', '');
        var r = parseInt(hex.slice(0, 2), 16);
        var g = parseInt(hex.slice(2, 4), 16);
        var b = parseInt(hex.slice(4, 6), 16);
        var a = (parseInt(range.value, 10) / 100).toFixed(2).replace(/0+$/, '').replace(/\.$/, '');

        value.value = 'rgba(' + r + ',' + g + ',' + b + ',' + (a === '' ? '0' : a) + ')';
        if (out) out.textContent = range.value + '%';

        applyTheme();
      };

      picker.addEventListener('input', compose);
      range.addEventListener('input', compose);
    });

    applyTheme();
  }

  /* ------------------------------------------------------ окно выбора картинки */

  /* Само окно живёт в assets/js/picker.js — оно общее с сайтом */
  function openPicker(input) {
    if (!window.KulagerPicker || !window.KulagerPicker.available) return;

    window.KulagerPicker.open(function (path) {
      input.value = path;
      input.dispatchEvent(new Event('change'));
    });
  }

  bindRichtext(document);
  bindImagePreview(document);

  /* ------------------------------------------------------ короткие сообщения */

  // Общему окну выбора нужен способ показать сообщение — отдаём свой
  window.KULAGER_FLASH = function (text, isError) { flash(text, isError); };

  function flash(text, isError) {
    var note = document.createElement('div');
    note.className = 'toast' + (isError ? ' toast--error' : '');
    note.textContent = text;

    document.body.appendChild(note);

    setTimeout(function () { note.classList.add('is-out'); }, 1600);
    setTimeout(function () { note.remove(); }, 2100);
  }
})();

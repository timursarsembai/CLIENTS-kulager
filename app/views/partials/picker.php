<?php
declare(strict_types=1);

/**
 * Окно выбора картинки: библиотека и загрузка.
 *
 * Один и тот же шаблон подключают админка и страница сайта в режиме
 * правки — чтобы выбор картинки выглядел и работал одинаково.
 */
?>
<div class="picker" data-picker hidden>
  <div class="picker__backdrop" data-picker-close></div>

  <div class="picker__window" role="dialog" aria-modal="true" aria-label="<?= ate('Выбор изображения') ?>">
    <div class="picker__head">
      <div class="picker__tabs">
        <button type="button" class="picker__tab is-active" data-picker-tab="library"><?= ate('Библиотека') ?></button>
        <button type="button" class="picker__tab" data-picker-tab="upload"><?= ate('Загрузить') ?></button>
      </div>
      <button type="button" class="picker__close" data-picker-close title="<?= ate('Закрыть') ?>">✕</button>
    </div>

    <div class="picker__body" data-picker-panel="library">
      <p class="picker__empty" data-picker-status><?= ate('Загружаем библиотеку…') ?></p>
      <div class="picker__grid" data-picker-grid></div>
    </div>

    <div class="picker__body" data-picker-panel="upload" hidden>
      <label class="picker__drop" data-picker-drop>
        <input type="file" name="files[]" accept="image/*" multiple data-picker-file hidden>
        <span class="picker__drop-title"><?= ate('Перетащите файлы сюда') ?></span>
        <span class="picker__drop-hint"><?= ate('или нажмите, чтобы выбрать на компьютере') ?></span>
      </label>
      <p class="picker__empty" data-picker-upload-status hidden></p>
    </div>
  </div>
</div>

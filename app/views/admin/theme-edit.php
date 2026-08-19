<?php
declare(strict_types=1);

/**
 * Правка одной темы. Цвета редактируются пипеткой, а значения вроде градиента
 * обложки и прозрачности фотографий — обычным полем: там не один цвет.
 *
 * Предпросмотр рисуется тут же на странице: подставляем переменные в кусок
 * вёрстки, чтобы не переключать тему на сайте ради проверки.
 *
 * @var Admin  $admin
 * @var array  $theme
 * @var array  $vars
 * @var array  $fields не используется: поля берутся из ThemeRepository::GROUPS
 */
?>
<div class="crumbs">
  <a href="<?= e($admin->url('themes')) ?>">Оформление</a> → <?= e((string) $theme['name']) ?>
</div>

<h1 class="page-title"><?= e((string) $theme['name']) ?></h1>

<form method="post" action="<?= e($admin->url('themes/' . $theme['id'])) ?>" class="theme-form" data-theme-form>
  <?= Csrf::field() ?>

  <div class="card">
    <label class="field">
      <span class="field__label">Название</span>
      <input type="text" name="name" value="<?= e((string) $theme['name']) ?>" required>
    </label>

    <div class="theme-swatch">
      <label class="field">
        <span class="field__label">Кружок: фон</span>
        <input type="color" name="swatch_bg" value="<?= e((string) $theme['swatch_bg'] ?: '#000000') ?>">
      </label>

      <label class="field">
        <span class="field__label">Кружок: акцент</span>
        <input type="color" name="swatch_accent" value="<?= e((string) $theme['swatch_accent'] ?: '#ffffff') ?>">
      </label>
    </div>
  </div>

  <div class="theme-columns">
    <div>
      <?php foreach (ThemeRepository::GROUPS as $group): ?>
        <div class="card">
          <h2 class="card__title"><?= e((string) $group['title']) ?></h2>
          <p class="card__lead"><?= e((string) $group['hint']) ?></p>

          <div class="theme-vars">
            <?php foreach ($group['vars'] as $key => [$label, $type, $where]): ?>
              <?php $value = (string) ($vars[$key] ?? ''); ?>

              <label class="field theme-var">
                <span class="field__label"><?= e($label) ?></span>

                <?php if ($type === 'color'): ?>
                  <span class="theme-var__pair">
                    <input type="color" value="<?= e(preg_match('~^#[0-9a-f]{6}$~i', $value) ? $value : '#000000') ?>"
                           data-theme-color>
                    <input type="text" name="vars[<?= e($key) ?>]" value="<?= e($value) ?>"
                           data-theme-value data-var="<?= e($key) ?>">
                  </span>

                <?php elseif ($type === 'alpha'): ?>
                  <?php
                  // rgba(8,19,31,0.98) — раскладываем на цвет и прозрачность,
                  // чтобы не заставлять вписывать числа руками
                  preg_match('~rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([0-9.]+))?~i', $value, $rgb);
                  $hex = $rgb ? sprintf('#%02x%02x%02x', (int) $rgb[1], (int) $rgb[2], (int) $rgb[3]) : '#000000';
                  $alpha = $rgb && isset($rgb[4]) ? (float) $rgb[4] : 1.0;
                  ?>
                  <span class="theme-var__pair" data-theme-alpha>
                    <input type="color" value="<?= e($hex) ?>" data-alpha-color>
                    <input type="range" min="0" max="100" step="1" value="<?= e((string) round($alpha * 100)) ?>"
                           data-alpha-range title="Прозрачность">
                    <output data-alpha-out><?= e((string) round($alpha * 100)) ?>%</output>
                    <input type="hidden" name="vars[<?= e($key) ?>]" value="<?= e($value) ?>"
                           data-theme-value data-var="<?= e($key) ?>">
                  </span>

                <?php else: ?>
                  <input type="text" name="vars[<?= e($key) ?>]" value="<?= e($value) ?>"
                         data-theme-value data-var="<?= e($key) ?>">
                <?php endif; ?>

                <span class="field__hint"><?= e($where) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card theme-preview-card">
      <h2 class="card__title">Предпросмотр</h2>

      <div class="theme-preview" data-theme-preview>
        <div class="theme-preview__header">
          <span class="theme-preview__logo">KULAGER</span>
          <span class="theme-preview__nav">Модели · Отрасли · Компания</span>
        </div>

        <div class="theme-preview__body">
          <div class="theme-preview__kicker">Надзаголовок</div>
          <div class="theme-preview__title">Заголовок страницы</div>
          <p class="theme-preview__text">
            Обычный текст блока и <a href="#" onclick="return false">ссылка внутри него</a>.
            Приглушённая строка — подпись под карточкой.
          </p>

          <div class="theme-preview__row">
            <span class="theme-preview__btn">Кнопка</span>
            <span class="theme-preview__btn theme-preview__btn--ghost">Контурная</span>
          </div>

          <div class="theme-preview__card">
            <div class="theme-preview__card-title">Карточка</div>
            <div class="theme-preview__muted">Текст на поверхности карточки</div>
          </div>
        </div>
      </div>

      <p class="muted">Предпросмотр обновляется сразу, сохранять для проверки не нужно.</p>
    </div>
  </div>

  <div class="block-form__actions">
    <button type="submit" class="btn btn--primary">Сохранить</button>
    <a href="<?= e($admin->url('themes')) ?>" class="btn">К списку тем</a>
  </div>
</form>

<?php
declare(strict_types=1);

/**
 * Медиатека: загрузка файлов и описания к ним.
 *
 * Выбор картинки для поля формы живёт не здесь, а в окне поверх формы
 * (см. picker в admin.js) — оно берёт файлы через media/json.
 *
 * @var Admin $admin
 * @var list<array> $items
 * @var bool $writable
 * @var bool $gd
 * @var string $limit
 */
?>
<h1 class="page-title"><?= ate('Медиатека') ?></h1>

<?php if (!$writable): ?>
  <div class="notice notice--error">
    Каталог <code>assets/uploads</code> недоступен для записи. На хостинге выставьте ему права 755
    (или 775), иначе загрузка работать не будет.
  </div>
<?php endif; ?>

<?php if (!$gd): ?>
  <div class="notice notice--warn"><?= ate('Расширение GD не установлено: файлы будут сохраняться как есть, без уменьшенных копий.') ?></div>
<?php endif; ?>

<div class="card">
  <h2 class="card__title"><?= ate('Загрузить') ?></h2>

  <form method="post" action="<?= e($admin->url('media/upload')) ?>" enctype="multipart/form-data">
    <?= Csrf::field() ?>

    <label class="field">
      <span class="field__label"><?= ate('Файлы') ?></span>
      <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/svg+xml" required>
      <span class="field__hint">
        JPEG, PNG, WebP или SVG. Не больше <?= e((string) $limit) ?> за файл.
        Для больших фотографий сами создадим уменьшенные копии.
      </span>
    </label>

    <button type="submit" class="btn btn--primary"><?= ate('Загрузить') ?></button>
  </form>
</div>

<div class="card">
  <div class="card__head">
    <h2 class="card__title"><?= ate('Файлы') ?></h2>
    <span class="muted"><?= e((string) count($items)) ?></span>
  </div>

  <?php if ($items === []): ?>
    <p class="muted"><?= ate('Пока пусто. Загрузите первые изображения.') ?></p>
  <?php else: ?>
    <div class="media-grid">
      <?php foreach ($items as $item): ?>
        <?php $url = '/assets/' . $item['path']; ?>
        <figure class="media" data-media-path="<?= e((string) $item['path']) ?>">
          <div class="media__thumb">
            <img src="<?= e($url) ?>" alt="<?= e((string) $item['alt_ru']) ?>" loading="lazy">
          </div>

          <figcaption class="media__body">
            <div class="media__name" title="<?= e((string) $item['path']) ?>"><?= e(basename((string) $item['path'])) ?></div>
            <div class="media__meta">
              <?= e((string) $item['width']) ?>×<?= e((string) $item['height']) ?> ·
              <?= e((string) round(((int) $item['size']) / 1024)) ?> КБ
            </div>

            <details class="media__details">
              <summary><?= ate('Описание') ?></summary>

              <form method="post" action="<?= e($admin->url('media/' . $item['id'] . '/alt')) ?>">
                <?= Csrf::field() ?>

                <label class="field">
                  <span class="field__label"><?= ate('Описание (рус)') ?></span>
                  <input type="text" name="alt_ru" value="<?= e((string) $item['alt_ru']) ?>">
                </label>

                <label class="field">
                  <span class="field__label"><?= ate('Описание (қаз)') ?></span>
                  <input type="text" name="alt_kk" value="<?= e((string) $item['alt_kk']) ?>">
                </label>

                <button type="submit" class="btn btn--small"><?= ate('Сохранить') ?></button>
              </form>
            </details>

            <form method="post" action="<?= e($admin->url('media/' . $item['id'] . '/delete')) ?>"
                  data-confirm="Удалить файл вместе с уменьшенными копиями?">
              <?= Csrf::field() ?>
              <button type="submit" class="link link--danger"><?= ate('Удалить') ?></button>
            </form>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

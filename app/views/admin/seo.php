<?php
declare(strict_types=1);

/**
 * Настройки для поисковиков и сводка по страницам.
 *
 * Общие поля задают то, что подставляется страницам без своих значений;
 * таблица ниже показывает, где эти значения так и остались незаполненными.
 *
 * @var Admin $admin
 * @var array $fields
 * @var array $counters поля счётчиков
 * @var array $values
 * @var list<array> $pages
 * @var array $locales
 */

$noindex = ($values['seo_noindex'] ?? '') !== '';
$withIssues = array_filter($pages, static fn (array $row): bool => $row['issues'] !== []);
?>
<h1 class="page-title">SEO</h1>

<?php if ($noindex): ?>
  <div class="notice notice--warn">
    <strong><?= ate('Сайт закрыт от индексации.') ?></strong> Во все страницы добавлен
    <code>noindex</code>, robots.txt запрещает обход, карта сайта пустая.
    Не забудьте снять галочку перед запуском.
  </div>
<?php endif; ?>

<div class="card card--form">
  <h2 class="card__title"><?= ate('Общие настройки') ?></h2>
  <p class="card__lead"><?= ate('Подставляются страницам, у которых своё значение не заполнено. Заголовок и описание каждой страницы правятся в её настройках.') ?></p>

  <form method="post" action="<?= e($admin->url('seo')) ?>">
    <?= Csrf::field() ?>

    <?php foreach ($fields as $key => $field): ?>
      <?php
      $value = (string) ($values[$key] ?? '');
      $type = (string) ($field['type'] ?? 'string');
      ?>

      <?php if ($type === 'bool'): ?>
        <label class="field field--check">
          <input type="hidden" name="<?= e($key) ?>" value="">
          <input type="checkbox" name="<?= e($key) ?>" value="1"<?= $value !== '' ? ' checked' : '' ?>>
          <span class="field__label"><?= e((string) $field['label']) ?></span>
          <?php if (isset($field['hint'])): ?>
            <span class="field__hint"><?= e((string) $field['hint']) ?></span>
          <?php endif; ?>
        </label>
      <?php else: ?>
        <label class="field">
          <span class="field__label"><?= e((string) $field['label']) ?></span>

          <?php if ($type === 'text'): ?>
            <textarea name="<?= e($key) ?>" rows="3"><?= e($value) ?></textarea>
          <?php elseif ($type === 'image'): ?>
            <?= FormBuilder::imageField($key, $value) ?>
          <?php else: ?>
            <input type="text" name="<?= e($key) ?>" value="<?= e($value) ?>">
          <?php endif; ?>

          <?php if (isset($field['hint'])): ?>
            <span class="field__hint"><?= e((string) $field['hint']) ?></span>
          <?php endif; ?>
        </label>
      <?php endif; ?>
    <?php endforeach; ?>

    <button type="submit" class="btn btn--primary"><?= ate('Сохранить') ?></button>
  </form>
</div>

<div class="card card--form">
  <h2 class="card__title"><?= ate('Счётчики и аналитика') ?></h2>
  <p class="card__lead"><?= ate('Для Яндекс.Метрики и Google Analytics достаточно номера — код соберём сами и поставим куда нужно. Остальные сервисы вставляются кодом как есть. Редактору счётчики не подключаются: собственные заходы считать незачем.') ?></p>

  <form method="post" action="<?= e($admin->url('seo')) ?>">
    <?= Csrf::field() ?>

    <?php foreach ($counters as $key => $field): ?>
      <?php $value = (string) ($values[$key] ?? ''); ?>
      <label class="field">
        <span class="field__label"><?= e((string) $field['label']) ?></span>

        <?php if (($field['type'] ?? '') === 'text'): ?>
          <textarea name="<?= e($key) ?>" rows="4"><?= e($value) ?></textarea>
        <?php else: ?>
          <input type="text" name="<?= e($key) ?>" value="<?= e($value) ?>">
        <?php endif; ?>

        <span class="field__hint"><?= e((string) ($field['hint'] ?? '')) ?></span>
      </label>
    <?php endforeach; ?>

    <button type="submit" class="btn btn--primary"><?= ate('Сохранить счётчики') ?></button>
  </form>
</div>

<div class="card">
  <div class="card__head">
    <h2 class="card__title"><?= ate('Страницы') ?></h2>
    <span class="muted">с замечаниями: <?= e((string) count($withIssues)) ?> из <?= e((string) count($pages)) ?></span>
  </div>

  <p class="card__lead"><?= ate('Заголовок читается в выдаче примерно до 70 знаков, описание — до 200. Длиннее не ошибка, но поисковик обрежет.') ?></p>

  <table class="table">
    <thead>
      <tr>
        <th><?= ate('Адрес') ?></th>
        <th><?= ate('Заголовок') ?></th>
        <th><?= ate('Описание') ?></th>
        <th><?= ate('Замечания') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pages as $row): ?>
        <tr>
          <td class="nowrap">
            <a href="<?= e($admin->url('page/' . $row['page_id'] . '/' . $row['locale'])) ?>">
              /<?= e($row['slug']) ?>
            </a>
            <span class="muted"><?= e(mb_strtoupper($row['locale'])) ?></span>
          </td>
          <td>
            <?= e(mb_substr($row['title'], 0, 60)) ?><?= $row['title_len'] > 60 ? '…' : '' ?>
            <span class="muted nowrap"><?= e((string) $row['title_len']) ?></span>
          </td>
          <td>
            <?= e(mb_substr($row['description'], 0, 60)) ?><?= $row['desc_len'] > 60 ? '…' : '' ?>
            <span class="muted nowrap"><?= e((string) $row['desc_len']) ?></span>
          </td>
          <td>
            <?php if ($row['issues'] === []): ?>
              <span class="pill pill--ok"><?= ate('в порядке') ?></span>
            <?php else: ?>
              <?php foreach ($row['issues'] as $issue): ?>
                <span class="pill pill--warn"><?= e($issue) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2 class="card__title"><?= ate('Служебные файлы') ?></h2>
  <p class="card__lead"><?= ate('Собираются на лету из того, что опубликовано прямо сейчас — обновлять вручную не нужно.') ?></p>

  <div class="btn-row">
    <a class="btn" href="/robots.txt" target="_blank" rel="noopener">robots.txt ↗</a>
    <a class="btn" href="/sitemap.xml" target="_blank" rel="noopener">sitemap.xml ↗</a>
  </div>
</div>

<?php
declare(strict_types=1);

/**
 * Редактор страницы: список блоков, настройки, публикация.
 *
 * @var Site   $site
 * @var Admin  $admin
 * @var array  $page
 * @var string $locale
 * @var ?array $localeRow
 * @var list<array> $blocks
 * @var array  $library
 * @var array  $otherLocales
 * @var ?array $undo
 */

$base = $admin->url('page/' . $page['id'] . '/' . $locale);
$published = (bool) ($localeRow['is_published'] ?? false);
$slug = (string) ($localeRow['slug'] ?? '');
$publicUrl = $site->url($slug, $locale);
$bar = json_decode((string) ($localeRow['bar_json'] ?? ''), true) ?: [];
?>
<div class="editor-head">
  <div>
    <div class="crumbs"><a href="<?= e($admin->url('pages')) ?>"><?= ate('Страницы') ?></a> → <?= e($page['page_key']) ?></div>
    <h1 class="page-title"><?= e((string) ($localeRow['title'] ?? $page['page_key'])) ?></h1>

    <div class="editor-head__meta">
      <span class="pill pill--<?= $published ? 'ok' : 'draft' ?>"><?= $published ? ate('опубликована') : ate('черновик') ?></span>
      <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">/<?= e($slug) ?> ↗</a>
      <a href="<?= e($publicUrl . (str_contains($publicUrl, '?') ? '&' : '?') . 'edit=1') ?>"
         target="_blank" rel="noopener"><?= ate('править на сайте') ?> ↗</a>
      <a href="<?= e($publicUrl . (str_contains($publicUrl, '?') ? '&' : '?') . 'preview=1') ?>" target="_blank" rel="noopener"><?= ate('Предпросмотр черновика') ?> ↗</a>
    </div>
  </div>

  <div class="editor-head__actions">
    <?php foreach ($otherLocales as $code => $meta): ?>
      <a class="btn" href="<?= e($admin->url('page/' . $page['id'] . '/' . $code)) ?>"><?= e($meta['short']) ?></a>
    <?php endforeach; ?>

    <?php if ($undo !== null): ?>
      <?php /* Возврат к состоянию до последней правки — копирование блоков не должно быть путём в один конец */ ?>
      <form method="post" action="<?= e($base . '/undo') ?>"
            data-confirm="<?= ate('Вернуть страницу к состоянию до последнего изменения') ?><?= $undo['comment'] !== '' ? ' («' . e((string) $undo['comment']) . '»)' : '' ?>?">
        <?= Csrf::field() ?>
        <button type="submit" class="btn" title="<?= e(trim(($undo['comment'] ?: at('последнее изменение')) . ', ' . $undo['created_at'])) ?>">
          <?= ate('Отменить') ?>
        </button>
      </form>
    <?php endif; ?>

    <form method="post" action="<?= e($base . ($published ? '/unpublish' : '/publish')) ?>">
      <?= Csrf::field() ?>
      <button type="submit" class="btn <?= $published ? '' : 'btn--primary' ?>">
        <?= $published ? ate('Снять с публикации') : ate('Опубликовать') ?>
      </button>
    </form>
  </div>
</div>

<div class="editor">
  <div class="editor__main">
    <div class="card">
      <div class="card__head">
        <h2 class="card__title"><?= ate('Блоки страницы') ?></h2>
        <span class="muted"><?= e((string) count($blocks)) ?></span>
      </div>

      <?php if (!$isDefault): ?>
        <?php
        $notTranslated = $translation['same'];
        $done = $translation['total'] - $notTranslated;
        ?>
        <div class="notice notice--<?= $blocks === [] || $notTranslated > 0 ? 'warn' : 'ok' ?>">
          <?php if ($blocks === []): ?>
            <?= ate('Перевода нет. Можно скопировать блоки из версии «%s» (%d) и заменить текст.',
                    (string) $site->localeMeta($baseLocale)['name'], (int) $baseBlocks) ?>
          <?php elseif ($notTranslated > 0): ?>
            <?= ate('Похоже, ещё не переведено блоков: %d из %d — их содержимое совпадает с основным языком.',
                    $notTranslated, (int) $translation['total']) ?>
          <?php else: ?>
            <?= ate('Все %d блоков отличаются от основного языка — перевод выглядит готовым.', (int) $translation['total']) ?>
          <?php endif; ?>

          <form method="post" action="<?= e($base . '/copy') ?>" style="margin-top: 10px"
                data-confirm="<?= ate('Блоки этой языковой версии будут заменены копией основного языка. Продолжить?') ?>">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn--small">
              <?= $blocks === [] ? ate('Скопировать блоки из основного языка') : ate('Заново скопировать из основного языка') ?>
            </button>
            <span class="field__hint"><?= ate('Блоки этой версии будут заменены копией основного языка — перевод придётся набрать заново. Если нажали случайно, вернёт кнопка «Отменить» вверху страницы.') ?></span>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($blocks === []): ?>
        <p class="muted"><?= ate('Блоков пока нет. Добавьте первый из библиотеки справа.') ?></p>
      <?php else: ?>
        <p class="hint-line"><?= at('Перетащите блок за %s, чтобы поменять порядок — он сохранится сам.', '<span class="grip">⋮⋮</span>') ?></p>

        <ol class="blocks" data-blocks data-reorder-url="<?= e($base . '/reorder') ?>" data-token="<?= e(Csrf::token()) ?>">
          <?php foreach ($blocks as $block): ?>
            <?php
            $id = (int) $block['_id'];
            $visible = (bool) $block['_visible'];
            // Показываем первое, за что глаз зацепится: заголовок блока
            $preview = (string) ($block['title'] ?? $block['form']['title'] ?? $block['callout']['title'] ?? '');
            ?>
            <li class="block<?= $visible ? '' : ' block--hidden' ?>" id="block-<?= e((string) $id) ?>" data-block-id="<?= e((string) $id) ?>">
              <span class="block__handle" data-block-handle title="<?= ate('Перетащите, чтобы поменять порядок') ?>">⋮⋮</span>

              <a class="block__body" href="<?= e($base . '/block/' . $id) ?>">
                <span class="block__type"><?= ate(Blocks::title((string) $block['type'])) ?></span>
                <?php if ($preview !== ''): ?>
                  <span class="block__preview"><?= e(mb_substr(strip_tags($preview), 0, 90)) ?></span>
                <?php endif; ?>
              </a>

              <span class="block__actions">
                <?php if (!$visible): ?><span class="pill pill--draft"><?= ate('скрыт') ?></span><?php endif; ?>

                <form method="post" action="<?= e($base . '/block/' . $id . '/toggle') ?>">
                  <?= Csrf::field() ?>
                  <button type="submit" class="icon-btn" title="<?= $visible ? ate('Скрыть на сайте') : ate('Показать на сайте') ?>"><?= $visible ? '👁' : '🚫' ?></button>
                </form>

                <form method="post" action="<?= e($base . '/block/' . $id . '/duplicate') ?>">
                  <?= Csrf::field() ?>
                  <button type="submit" class="icon-btn" title="<?= ate('Скопировать') ?>">⧉</button>
                </form>

                <form method="post" action="<?= e($base . '/block/' . $id . '/delete') ?>" data-confirm="<?= ate('Удалить блок безвозвратно?') ?>">
                  <?= Csrf::field() ?>
                  <button type="submit" class="icon-btn icon-btn--danger" title="<?= ate('Удалить') ?>">✕</button>
                </form>
              </span>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="card__title"><?= ate('Настройки страницы') ?></h2>

      <form method="post" action="<?= e($base . '/settings') ?>">
        <?= Csrf::field() ?>

        <label class="field">
          <span class="field__label"><?= ate('Заголовок страницы') ?> <i><?= ate('обязательно') ?></i></span>
          <input type="text" name="title" value="<?= e((string) ($localeRow['title'] ?? '')) ?>"
                 data-slug-source required>
          <span class="field__hint"><?= ate('Виден во вкладке браузера и в результатах поиска.') ?></span>
        </label>

        <?php /* Адрес идёт следом за заголовком: он из него и получается */ ?>
        <label class="field">
          <span class="field__label"><?= ate('Адрес') ?></span>

          <span class="slug-field">
            <input type="text" name="slug" value="<?= e($slug) ?>" placeholder="otrasli/teplicy"
                   data-slug-input>
            <button type="button" class="btn btn--small" data-slug-make><?= ate('Из заголовка') ?></button>
          </span>

          <span class="field__hint"><?= ate('Заполняется сам из заголовка — кириллица переводится в латиницу. Свой адрес тоже можно вписать: без слэша в начале, пусто — главная страница.') ?></span>
        </label>

        <label class="field">
          <span class="field__label"><?= ate('Описание для поиска') ?></span>
          <textarea name="description" rows="3"><?= e((string) ($localeRow['description'] ?? '')) ?></textarea>
        </label>

        <label class="field">
          <span class="field__label"><?= ate('Картинка для соцсетей') ?></span>
          <?= FormBuilder::imageField('og_image', (string) ($localeRow['og_image'] ?? '')) ?>
          <span class="field__hint"><?= ate('Пусто — возьмётся общая из раздела «SEO».') ?></span>
        </label>

        <label class="field field--check">
          <input type="hidden" name="noindex" value="0">
          <input type="checkbox" name="noindex" value="1"<?= !empty($localeRow['noindex']) ? ' checked' : '' ?>>
          <span class="field__label"><?= ate('Закрыть от индексации') ?></span>
          <span class="field__hint"><?= ate('Страница останется на сайте, но пропадёт из карты сайта и поиска.') ?></span>
        </label>

        <label class="field">
          <span class="field__label"><?= ate('Канонический адрес') ?></span>
          <input type="text" name="canonical" value="<?= e((string) ($localeRow['canonical'] ?? '')) ?>"
                 placeholder="otrasli/sklady">
          <span class="field__hint"><?= ate('Заполняют, когда страница дублирует другую: поисковик засчитает ту.') ?></span>
        </label>

        <fieldset class="group">
          <legend><?= ate('Плавающая панель внизу') ?></legend>
          <p class="group__hint"><?= ate('Появляется, когда посетитель уходит ниже первого экрана. Пусто — берётся общий для сайта текст.') ?></p>

          <label class="field">
            <span class="field__label"><?= ate('Заголовок') ?></span>
            <input type="text" name="bar_title" value="<?= e((string) ($bar['title'] ?? '')) ?>">
          </label>

          <label class="field">
            <span class="field__label"><?= ate('Подпись') ?></span>
            <input type="text" name="bar_subtitle" value="<?= e((string) ($bar['subtitle'] ?? '')) ?>">
          </label>

          <label class="field">
            <span class="field__label"><?= ate('Подпись кнопки') ?></span>
            <input type="text" name="bar_label" value="<?= e((string) ($bar['label'] ?? '')) ?>">
          </label>

          <label class="field">
            <span class="field__label"><?= ate('Сообщение в WhatsApp') ?></span>
            <textarea name="bar_message" rows="2"><?= e((string) ($bar['message'] ?? '')) ?></textarea>
          </label>
        </fieldset>

        <button type="submit" class="btn btn--primary"><?= ate('Сохранить настройки') ?></button>
      </form>
    </div>
  </div>

  <aside class="editor__side">
    <div class="card card--sticky">
      <h2 class="card__title"><?= ate('Добавить блок') ?></h2>

      <?php foreach ($library as $group => $types): ?>
        <div class="library__group">
          <div class="library__title"><?= ate((string) $group) ?></div>

          <?php foreach ($types as $type => $definition): ?>
            <form method="post" action="<?= e($base . '/add') ?>" class="library__item">
              <?= Csrf::field() ?>
              <input type="hidden" name="type" value="<?= e((string) $type) ?>">
              <button type="submit" class="library__btn" title="<?= ate((string) ($definition['hint'] ?? '')) ?>">
                <span class="library__name"><?= ate((string) $definition['title']) ?></span>
                <?php if (isset($definition['hint'])): ?>
                  <span class="library__hint"><?= e(mb_substr(at((string) $definition['hint']), 0, 80)) ?></span>
                <?php endif; ?>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>
</div>

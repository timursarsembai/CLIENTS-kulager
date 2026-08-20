<?php
declare(strict_types=1);

/**
 * Панель администратора поверх сайта — видна только тому, кто вошёл в админку.
 *
 * Отсюда попадают в редактор именно этой страницы и включают правку текста
 * прямо на месте. Гость панель не получает: её нет в разметке вовсе.
 *
 * Каждый пункт — значок с подписью. На телефоне подписи прячутся, и панель
 * остаётся одной строкой: подписями она переносилась и накрывала шапку сайта.
 *
 * @var Site   $site
 * @var View   $view
 * @var string $adminBase
 * @var int    $pageId
 */

$editing = $site->editMode();
$path = $site->path();

/* Ссылка «вкл/выкл правку» — тот же адрес с параметром или без него */
$current = $site->url($path);
$editUrl = $current . (str_contains($current, '?') ? '&' : '?') . 'edit=1';

/** Значки: 16×16, рисуются цветом текста — панель одна на все темы. */
$icons = [
    'admin' => '<path d="M2 2h5v5H2zM9 2h5v5H9zM2 9h5v5H2zM9 9h5v5H9z"/>',
    'pencil' => '<path d="M10.5 2.5l3 3-8 8H2.5v-3z"/>',
    'close'  => '<path d="M3.5 3.5l9 9M12.5 3.5l-9 9"/>',
    'mail'   => '<path d="M1.5 3.5h13v9h-13z"/><path d="M1.5 4.5L8 9l6.5-4.5"/>',
    'image'  => '<path d="M1.5 2.5h13v11h-13z"/><path d="M2 11.5l3.5-3.5 2.5 2.5 2-2 4 4"/><circle cx="5.5" cy="6" r="1.2"/>',
];

/** Пункт панели: значок плюс подпись, скрываемая на узком экране. */
$item = static function (string $href, string $icon, string $label, string $extra = '') use ($icons): void {
    ?>
    <a class="adminbar__item<?= $extra ?>" href="<?= e($href) ?>"
       title="<?= e($label) ?>" aria-label="<?= e($label) ?>">
      <svg class="adminbar__icon" width="16" height="16" viewBox="0 0 16 16"
           fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
        <?= $icons[$icon] ?>
      </svg>
      <span class="adminbar__label"><?= e($label) ?></span>
    </a>
    <?php
};
?>
<div class="adminbar">
  <a class="adminbar__brand" href="<?= e($adminBase) ?>" title="KULAGER">KULAGER</a>

  <?php if ($pageId > 0): ?>
    <?php $item($adminBase . '/page/' . $pageId . '/' . $site->locale(), 'admin', 'Править в админке'); ?>
  <?php endif; ?>

  <?php if ($editing): ?>
    <span class="adminbar__state">Правка включена — щёлкните по тексту</span>
    <?php $item($current, 'close', 'Выйти из правки', ' adminbar__item--accent'); ?>
  <?php else: ?>
    <?php $item($editUrl, 'pencil', 'Править на странице', ' adminbar__item--accent'); ?>
  <?php endif; ?>

  <span class="adminbar__gap"></span>

  <span class="adminbar__status" data-edit-status></span>

  <?php $item($adminBase . '/leads', 'mail', 'Заявки'); ?>
  <?php $item($adminBase . '/media', 'image', 'Медиатека'); ?>
</div>

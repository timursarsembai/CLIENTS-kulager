<?php
declare(strict_types=1);

/**
 * Панель администратора поверх сайта — видна только тому, кто вошёл в админку.
 *
 * Отсюда попадают в редактор именно этой страницы и включают правку текста
 * прямо на месте. Гость панель не получает: её нет в разметке вовсе.
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
?>
<div class="adminbar">
  <a class="adminbar__brand" href="<?= e($adminBase) ?>">KULAGER</a>

  <?php if ($pageId > 0): ?>
    <a class="adminbar__item" href="<?= e($adminBase . '/page/' . $pageId . '/' . $site->locale()) ?>">
      Править в админке
    </a>
  <?php endif; ?>

  <?php if ($editing): ?>
    <span class="adminbar__state">Правка включена — щёлкните по тексту</span>
    <a class="adminbar__item adminbar__item--accent" href="<?= e($current) ?>">Выйти из правки</a>
  <?php else: ?>
    <a class="adminbar__item adminbar__item--accent" href="<?= e($editUrl) ?>">Править на странице</a>
  <?php endif; ?>

  <span class="adminbar__gap"></span>

  <span class="adminbar__status" data-edit-status></span>

  <a class="adminbar__item" href="<?= e($adminBase . '/leads') ?>">Заявки</a>
  <a class="adminbar__item" href="<?= e($adminBase . '/media') ?>">Медиатека</a>
</div>

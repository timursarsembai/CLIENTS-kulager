<?php
declare(strict_types=1);

/**
 * Отрасли применения. Список берётся из общей навигации,
 * чтобы он не расходился с меню и подвалом.
 *
 * @var Site  $site
 * @var View  $view
 * @var array $block
 */

$groups = $site->nav('industries');
?>
<section id="<?= e($block['id'] ?? 'industries') ?>" class="wrap section">
  <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
  <h2 class="h2" style="max-width: 22ch"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
  <?php if (!empty($block['lead'])): ?>
    <p class="lead" style="max-width: 54ch"<?= $view->editable($block, 'lead') ?>><?= e($block['lead']) ?></p>
  <?php endif; ?>

  <div class="grid-lines" style="--min: 260px; margin-top: 24px">
    <?php foreach ($groups as $group): ?>
      <div class="cell industries__col">
        <h3<?= $view->editableNav($group) ?>><?= e($group['title']) ?></h3>
        <div class="industries__links">
          <?php foreach ($group['items'] as $item): ?>
            <a href="<?= e($site->url($item['url'])) ?>"<?= $view->editableNav($item, 'title') ?>><?= e($item['title']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

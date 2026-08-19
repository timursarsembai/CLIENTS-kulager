<?php
declare(strict_types=1);

/** @var View $view @var array $block */
?>
<section id="<?= e($block['id'] ?? 'vs') ?>" class="band">
  <div class="wrap section">
    <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
    <h2 class="h2" style="max-width: 24ch"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
    <p class="lead"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

    <div class="stats">
      <?php foreach ($block['items'] as $index => $item): ?>
        <div class="stats__item<?= !empty($item['highlight']) ? ' stats__item--highlight' : '' ?>">
          <div class="stats__value" data-count<?= $view->editable($block, "items.$index.value") ?>><?= e($item['value']) ?></div>
          <h3 class="stats__title"<?= $view->editable($block, "items.$index.title") ?>><?= e($item['title']) ?></h3>
          <p class="stats__text"<?= $view->editable($block, "items.$index.text") ?>><?= e($item['text']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
declare(strict_types=1);

/** @var View $view @var array $block */
?>
<section class="band band--surface-3">
  <div class="wrap testdrive">
    <div>
      <h2<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
      <p<?= $view->editable($block, 'text') ?>><?= e($block['text'] ?? '') ?></p>
    </div>
    <?= $view->partial('action', [
        'action' => $block['action'],
        'edit'   => $view->editable($block, 'action.label'),
      ]) ?>
  </div>
</section>

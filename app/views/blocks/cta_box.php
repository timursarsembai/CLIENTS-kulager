<?php
declare(strict_types=1);

/** @var View $view @var array $block */
?>
<section id="<?= e($block['id'] ?? 'lead') ?>" class="wrap section--cta"
         style="padding-bottom: clamp(24px, 3vw, 40px)">
  <div class="cta-box">
    <h2<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
    <p<?= $view->editable($block, 'text') ?>><?= e($block['text'] ?? '') ?></p>

    <div class="btn-row" style="align-items: center">
      <?php foreach ($block['actions'] as $index => $action): ?>
        <?= $view->partial('action', ['action' => $action, 'edit' => $view->editable($block, "actions.$index.label")]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

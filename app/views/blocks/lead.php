<?php
declare(strict_types=1);

/** @var Site $site @var View $view @var array $block */

$form = $block['form'];
?>
<section id="<?= e($block['id'] ?? 'lead') ?>" class="wrap" style="margin-top: clamp(48px, 6vw, 96px)">
  <div class="lead-block">
    <div class="lead-block__col">
      <div class="kicker" style="margin-bottom: 20px"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
      <h2 class="lead-block__title"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>

      <div class="lead-block__address">
        <?php foreach ($block['address'] as $index => $item): ?>
          <div>
            <div class="field-label"<?= $view->editable($block, "address.$index.label") ?>><?= e($item['label']) ?></div>
            <div<?= $view->editable($block, "address.$index.value", 'html') ?>><?= $item['value'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($block['download'])): ?>
        <a href="<?= e($block['download']['url']) ?>" target="_blank" rel="noopener" class="btn btn--ghost btn--sm" style="margin-top: 26px"<?= $view->editable($block, 'download.label') ?>><?= e($block['download']['label']) ?></a>
      <?php endif; ?>
    </div>

    <div class="lead-block__col lead-block__col--form">
      <h2 class="lead-block__form-title"<?= $view->editable($block, 'form.title') ?>><?= e($form['title']) ?></h2>
      <p style="font-size: 16px; line-height: 1.5; color: var(--text-5); margin: 0 0 26px; max-width: 40ch"<?= $view->editable($block, 'form.text') ?>><?= e($form['text']) ?></p>

      <div class="btn-row">
        <?php foreach ($form['actions'] as $index => $action): ?>
          <?= $view->partial('action', ['action' => $action, 'edit' => $view->editable($block, "form.actions.$index.label")]) ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

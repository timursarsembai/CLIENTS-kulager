<?php
declare(strict_types=1);

/** @var Site $site @var View $view @var array $block */
?>
<section id="<?= e($block['id'] ?? '') ?>" class="band band--surface-3">
  <div class="wrap split split--cta" style="padding-top: clamp(40px, 5vw, 76px); padding-bottom: clamp(40px, 5vw, 76px)">
    <div>
      <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
      <h2 class="h2" style="font-size: clamp(26px, 3.2vw, 42px); line-height: 1.06; max-width: 24ch"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
      <p class="lead" style="font-size: 17px; color: var(--text-5); max-width: 46ch; margin-bottom: 26px"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

      <div class="btn-row">
        <?php foreach ($block['actions'] ?? [] as $index => $action): ?>
          <?= $view->partial('action', ['action' => $action, 'edit' => $view->editable($block, "actions.$index.label")]) ?>
        <?php endforeach; ?>
      </div>
    </div>

    <img src="<?= e($site->asset($block['image'])) ?>" alt="<?= e($block['alt'] ?? '') ?>" style="width: 100%; aspect-ratio: 4/3; object-fit: cover; border: 1px solid var(--line)">
  </div>
</section>

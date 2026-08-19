<?php
declare(strict_types=1);

/** @var Site $site @var View $view @var array $block */
?>
<section class="hero">
  <img data-drift src="<?= e($site->asset($block['image'])) ?>" alt="<?= e($block['alt'] ?? '') ?>" class="hero__photo">
  <div class="hero__scrim"></div>

  <div class="hero__body">
    <div class="hero__inner">
      <div class="hero__eyebrow" data-hero-in style="--d: 0s">
        <span class="hero__rule"></span>
        <span class="hero__label"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></span>
      </div>

      <h1 class="h1" data-hero-in style="--d: .09s"<?= $view->editable($block, 'title', 'html') ?>><?= $block['title'] ?? '' ?></h1>

      <p class="hero__lead" data-hero-in style="--d: .18s"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

      <?php foreach ($block['actions'] ?? [] as $index => $action): ?>
        <?= $view->partial('action', ['action' => $action + ['hero_delay' => '.27s'], 'edit' => $view->editable($block, "actions.$index.label")]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

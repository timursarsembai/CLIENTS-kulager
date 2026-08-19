<?php
declare(strict_types=1);

/** @var Site $site @var View $view @var array $block */
?>
<section class="hero">
  <img src="<?= e($site->asset($block['image'])) ?>"<?= $view->editableImage($block, 'image') ?> alt="<?= e($block['alt'] ?? '') ?>" class="hero__photo">
  <div class="hero__scrim"></div>

  <div class="hero__body">
    <div class="hero__inner">
      <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
      <h2 class="h2" style="font-size: clamp(28px, 4.4vw, 60px); max-width: 18ch; margin-bottom: 16px"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
      <p class="lead" style="max-width: 44ch; margin-bottom: 28px"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

      <div class="photo-band__stats">
        <?php foreach ($block['stats'] as $index => $stat): ?>
          <div class="photo-band__stat">
            <div class="photo-band__value"<?= $view->editable($block, "stats.$index.value") ?>><?= e($stat['value']) ?></div>
            <div class="photo-band__caption"<?= $view->editable($block, "stats.$index.caption") ?>><?= e($stat['caption']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

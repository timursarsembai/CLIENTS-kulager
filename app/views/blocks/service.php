<?php
declare(strict_types=1);

/** @var Site $site @var View $view @var array $block */
?>
<section id="<?= e($block['id'] ?? 'service') ?>" class="band band--top" style="border-bottom: 0">
  <div class="wrap section--tight split">
    <div>
      <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
      <h2 class="h2" style="margin-bottom: 18px"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
      <p class="lead" style="max-width: 40ch; margin-bottom: 26px"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

      <div class="check-list">
        <?php foreach ($block['points'] as $index => $point): ?>
          <div><span class="check">✓</span><span<?= $view->editable($block, "points.$index") ?>><?= e($point) ?></span></div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($block['quote'])): ?>
        <div class="pull-quote">
          <p<?= $view->editable($block, 'quote', 'html') ?>><?= $block['quote'] ?></p>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($block['image'])): ?>
      <img src="<?= e($site->asset($block['image'])) ?>" alt="<?= e($block['alt'] ?? '') ?>" style="width: 100%; border: 1px solid var(--line)">
    <?php endif; ?>
  </div>
</section>

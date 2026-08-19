<?php
declare(strict_types=1);

/** @var View $view @var array $block */

$body = static function () use ($block, $view): void { ?>
  <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
  <h2 class="h2" style="max-width: 24ch"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
  <p class="lead" style="max-width: 56ch"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

  <div class="steps">
    <?php foreach ($block['items'] as $index => $item): ?>
      <div class="steps__item">
        <span class="steps__num"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
        <div>
          <h3 class="steps__title"<?= $view->editable($block, "items.$index.title") ?>><?= e($item['title']) ?></h3>
          <p class="text"<?= $view->editable($block, "items.$index.text") ?>><?= e($item['text']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($block['action'])): ?>
    <div style="margin-top: 32px"><?= $view->partial('action', [
        'action' => $block['action'],
        'edit'   => $view->editable($block, 'action.label'),
      ]) ?></div>
  <?php endif; ?>
<?php };
?>
<?php if (!empty($block['panel'])): ?>
  <section id="<?= e($block['id'] ?? '') ?>" class="band">
    <div class="wrap section"><?php $body(); ?></div>
  </section>
<?php else: ?>
  <section id="<?= e($block['id'] ?? '') ?>" class="wrap section"><?php $body(); ?></section>
<?php endif; ?>

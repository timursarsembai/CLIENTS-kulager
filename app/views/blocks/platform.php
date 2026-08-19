<?php
declare(strict_types=1);

/**
 * Платформа и сменные надстройки. Два исполнения:
 * с карточкой базового шасси (главная) или с фотографией (внутренние страницы).
 *
 * @var Site  $site
 * @var View  $view
 * @var array $block
 */

$base = $block['base'] ?? null;
?>
<section id="<?= e($block['id'] ?? 'platform') ?>" class="wrap section">
  <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
  <h2 class="h2" style="max-width: <?= $base ? '22ch' : '26ch' ?>"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
  <p class="lead" style="margin-bottom: <?= $base ? '40px' : '36px' ?>; max-width: 56ch"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

  <div class="platform" style="<?= $base ? '' : '--min: 420px' ?>">
    <?php if ($base): ?>
    <div class="platform__base">
      <div class="kicker kicker--sm" style="font-weight: 700; margin-bottom: 16px"<?= $view->editable($block, 'base.label') ?>><?= e($base['label']) ?></div>
      <img src="<?= e($site->asset($base['image'])) ?>" alt="<?= e($base['alt']) ?>">
      <h3 class="h3" style="font-size: 22px; line-height: 1.12"<?= $view->editable($block, 'base.title') ?>><?= e($base['title']) ?></h3>
      <p class="text" style="margin-bottom: 18px; color: var(--text-5)"<?= $view->editable($block, 'base.text') ?>><?= e($base['text']) ?></p>
      <div class="platform__specs">
        <?php foreach ($base['specs'] as $specIndex => $spec): ?>
          <div><span style="color: var(--accent)">—</span><span<?= $view->editable($block, "base.specs.$specIndex") ?>><?= e($spec) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="platform__options">
      <?php foreach ($block['options'] as $index => $option): ?>
        <div class="platform__option<?= !empty($option['highlight']) ? ' platform__option--highlight' : '' ?>">
          <span class="platform__num"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
          <div>
            <h4 class="h4"<?= $view->editable($block, "options.$index.title") ?>><?= e($option['title']) ?></h4>
            <p class="text"<?= !empty($option['highlight']) ? ' style="color: var(--text-5)"' : '' ?><?= $view->editable($block, "options.$index.text") ?>><?= e($option['text']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$base && !empty($block['image'])): ?>
      <div>
        <img src="<?= e($site->asset($block['image'])) ?>" alt="<?= e($block['alt'] ?? '') ?>" style="width: 100%; aspect-ratio: 4/3; object-fit: cover; border: 1px solid var(--line)">
        <?php if (!empty($block['caption'])): ?>
          <p class="note" style="margin-top: 12px"<?= $view->editable($block, 'caption') ?>><?= e($block['caption']) ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($block['note'])): ?>
    <p class="note" style="margin-top: 28px; max-width: 62ch"<?= $view->editable($block, 'note') ?>><?= e($block['note']) ?></p>
  <?php endif; ?>
</section>

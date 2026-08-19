<?php
declare(strict_types=1);

/** @var View $view @var array $block */

[$columnA, $columnB] = $block['columns'];
?>
<section class="wrap">
  <div id="<?= e($block['id'] ?? 'compare') ?>" class="compare">
    <div class="kicker" style="margin-bottom: 20px"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
    <h2 class="h2" style="font-size: clamp(24px, 2.8vw, 36px); line-height: 1.08; margin-bottom: 12px"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
    <p class="lead" style="font-size: 17px; color: var(--text-3); margin-bottom: 28px"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

    <div class="compare__scroll">
      <div class="compare__grid">
        <div class="compare__col compare__col--a">
          <div class="compare__head">
            <span class="compare__rule"></span>
            <span class="compare__name"><?= e($columnA) ?></span>
          </div>
          <div style="display: grid">
            <?php foreach ($block['rows'] as $index => $row): ?>
              <div class="compare__row">
                <div class="compare__key"<?= $view->editable($block, "rows.$index.name") ?>><?= e($row['name']) ?></div>
                <div class="compare__val"<?= $view->editable($block, "rows.$index.a") ?>><?= e($row['a']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="compare__col compare__col--b">
          <div class="compare__head">
            <span class="compare__rule"></span>
            <span class="compare__name"><?= e($columnB) ?></span>
          </div>
          <div style="display: grid">
            <?php foreach ($block['rows'] as $index => $row): ?>
              <div class="compare__row">
                <div class="compare__key"<?= $view->editable($block, "rows.$index.name") ?>><?= e($row['name']) ?></div>
                <div class="compare__val"<?= $view->editable($block, "rows.$index.b") ?>><?= e($row['b']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($block['note'])): ?>
      <p style="font-size: 14px; color: var(--text-4); margin: 16px 0 0"<?= $view->editable($block, 'note') ?>><?= e($block['note']) ?></p>
    <?php endif; ?>
  </div>
</section>

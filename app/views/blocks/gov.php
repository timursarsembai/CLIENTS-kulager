<?php
declare(strict_types=1);

/** @var Site $site @var View $view @var array $block */

$callout = $block['callout'] ?? null;
?>
<section id="<?= e($block['id'] ?? 'gov') ?>" class="band band--top">
  <div class="wrap section--tight">
    <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
    <h2 class="h2" style="max-width: 22ch"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
    <p class="lead" style="max-width: 54ch"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

    <div class="grid-gap" style="--min: 340px; margin-bottom: 32px">
      <?php foreach ($block['cards'] as $index => $card): ?>
        <article class="card card--2" data-zoom>
          <img src="<?= e($site->asset($card['image'])) ?>" alt="<?= e($card['alt']) ?>">
          <div class="card__body card__body--md">
            <div class="kicker kicker--sm"<?= $view->editable($block, "cards.$index.kicker") ?>><?= e($card['kicker']) ?></div>
            <h3 class="card__title card__title--md"<?= $view->editable($block, "cards.$index.title") ?>><?= e($card['title']) ?></h3>
            <p class="card__text" style="margin-bottom: 18px"<?= $view->editable($block, "cards.$index.text") ?>><?= e($card['text']) ?></p>

            <?php if (!empty($card['specs'])): ?>
              <div class="dash-list" style="border-top: 1px solid var(--line); padding-top: 16px">
                <?php foreach ($card['specs'] as $specIndex => $spec): ?>
                  <div><span class="dash">—</span><span<?= $view->editable($block, "cards.$index.specs.$specIndex") ?>><?= e($spec) ?></span></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($card['link'])): ?>
              <div style="border-top: 1px solid var(--line); padding-top: 16px">
                <a href="<?= e($site->url($card['link']['url'])) ?>" style="font-size: 16px; font-weight: 700"<?= $view->editable($block, "cards.$index.link.label") ?>><?= e($card['link']['label']) ?></a>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($callout['title'])): ?>
      <div class="callout">
        <div>
          <h3<?= $view->editable($block, 'callout.title') ?>><?= e($callout['title']) ?></h3>
          <p<?= $view->editable($block, 'callout.text') ?>><?= e($callout['text'] ?? '') ?></p>
        </div>
        <?php if (!empty($callout['action']['label'])): ?>
          <?= $view->partial('action', ['action' => $callout['action'], 'edit' => $view->editable($block, 'callout.action.label')]) ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

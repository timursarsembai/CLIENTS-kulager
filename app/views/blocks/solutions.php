<?php
declare(strict_types=1);

/** @var Site $site @var View $view @var array $block */

$featured = $block['featured'];
?>
<section id="<?= e($block['id'] ?? 'solutions') ?>" class="wrap section section--flush-bottom">
  <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
  <h2 class="h2" style="max-width: 20ch; margin-bottom: 12px"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
  <p class="lead"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

  <article class="solution">
    <img src="<?= e($site->asset($featured['image'])) ?>"<?= $view->editableImage($block, 'featured.image') ?> alt="<?= e($featured['alt']) ?>">
    <div class="solution__body">
      <div class="badge-row">
        <span class="kicker kicker--sm" style="margin-bottom: 0"<?= $view->editable($block, 'featured.kicker') ?>><?= e($featured['kicker']) ?></span>
        <span class="badge badge--<?= e($featured['badge']['style']) ?>"<?= $view->editable($block, 'featured.badge.label') ?>><?= e($featured['badge']['label']) ?></span>
      </div>
      <h3 class="solution__title"<?= $view->editable($block, 'featured.title') ?>><?= e($featured['title']) ?></h3>
      <p style="margin: 0 0 22px; font-size: 17px; line-height: 1.5; color: var(--text-3)"<?= $view->editable($block, 'featured.text') ?>><?= e($featured['text']) ?></p>
      <div class="solution__figure">
        <span class="solution__value"<?= $view->editable($block, 'featured.figure.value') ?>><?= e($featured['figure']['value']) ?></span>
        <span style="font-size: 14px; color: var(--text-4)"<?= $view->editable($block, 'featured.figure.caption') ?>><?= e($featured['figure']['caption']) ?></span>
      </div>
      <a href="<?= e($site->url($featured['link']['url'])) ?>" style="font-size: 16px; font-weight: 700"<?= $view->editable($block, 'featured.link.label') ?>><?= e($featured['link']['label']) ?></a>
    </div>
  </article>

  <div class="grid-gap" style="--min: 400px">
    <?php foreach ($block['cards'] as $index => $card): ?>
      <article class="card" data-zoom>
        <img src="<?= e($site->asset($card['image'])) ?>"<?= $view->editableImage($block, "cards.$index.image") ?> alt="<?= e($card['alt']) ?>">
        <div class="card__body">
          <div class="badge-row" style="margin-bottom: 14px">
            <span class="kicker kicker--sm" style="margin-bottom: 0"<?= $view->editable($block, "cards.$index.kicker") ?>><?= e($card['kicker']) ?></span>
            <span class="badge badge--<?= e($card['badge']['style']) ?>"<?= $view->editable($block, "cards.$index.badge.label") ?>><?= e($card['badge']['label']) ?></span>
          </div>
          <h3 class="card__title"<?= $view->editable($block, "cards.$index.title") ?>><?= e($card['title']) ?></h3>
          <p class="card__text"<?= $view->editable($block, "cards.$index.text") ?>><?= e($card['text']) ?></p>

          <div class="card__foot<?= isset($card['steps']) ? ' card__foot--steps' : '' ?>">
            <?php foreach ($card['tags'] ?? [] as $tagIndex => $tag): ?>
              <span class="tag"<?= $view->editable($block, "cards.$index.tags.$tagIndex") ?>><?= e($tag) ?></span>
            <?php endforeach; ?>

            <?php foreach ($card['steps'] ?? [] as $i => $step): ?>
              <span><span class="step-num"><?= e((string) ($i + 1)) ?></span> <span<?= $view->editable($block, "cards.$index.steps.$i") ?>><?= e($step) ?></span></span>
            <?php endforeach; ?>

            <a href="<?= e($site->url($card['link']['url'])) ?>" class="card__link"<?= $view->editable($block, "cards.$index.link.label") ?>><?= e($card['link']['label']) ?></a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php $special = $block['special'] ?? null; ?>
  <?php if ($special): ?>
    <div style="margin-top: clamp(36px, 4vw, 64px)">
      <div class="subhead">
        <h3<?= $view->editable($block, 'special.title') ?>><?= e($special['title']) ?></h3>
        <span<?= $view->editable($block, 'special.subtitle') ?>><?= e($special['subtitle']) ?></span>
      </div>

      <div class="grid-lines" style="--min: 280px">
        <?php foreach ($special['items'] as $index => $item): ?>
          <article class="card card--flat card--3x2" data-zoom>
            <img src="<?= e($site->asset($item['image'])) ?>"<?= $view->editableImage($block, "special.items.$index.image") ?> alt="<?= e($item['alt']) ?>">
            <div class="card__body card__body--sm">
              <div class="badge-row" style="gap: 10px; margin-bottom: 12px">
                <span class="kicker kicker--xs"<?= $view->editable($block, "special.items.$index.kicker") ?>><?= e($item['kicker']) ?></span>
                <span class="badge badge--<?= e($item['badge']['style']) ?>"><?= e($item['badge']['label']) ?></span>
              </div>
              <h4 class="h4" style="font-size: 19px; margin-bottom: 10px"<?= $view->editable($block, "special.items.$index.title") ?>><?= e($item['title']) ?></h4>
              <p class="text"<?= $view->editable($block, "special.items.$index.text") ?>><?= e($item['text']) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>

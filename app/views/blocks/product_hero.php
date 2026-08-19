<?php
declare(strict_types=1);

/**
 * Верхний экран внутренней страницы: галерея снимков, заголовок,
 * карточка «цена по запросу» и короткие характеристики.
 * Используется и отраслями, и моделями.
 *
 * @var Site  $site
 * @var View  $view
 * @var array $block
 */

$offer = $block['offer'] ?? null;
?>
<section id="gallery" class="wrap" style="padding-top: clamp(24px, 3vw, 44px); padding-bottom: clamp(48px, 6vw, 96px)">
  <div class="product__eyebrow">
    <span class="hero__rule"></span>
    <span class="hero__label"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></span>
  </div>

  <div class="product">
    <div data-gallery>
      <div class="product__stage">
        <img src="<?= e($site->asset($block['gallery'][0])) ?>" alt="<?= e($block['alt'] ?? '') ?>" data-gallery-stage>
      </div>
      <div class="product__thumbs">
        <?php foreach ($block['gallery'] as $index => $shot): ?>
          <button type="button"
                  class="product__thumb"
                  data-gallery-thumb="<?= e($site->asset($shot)) ?>"
                  aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                  aria-label="Снимок <?= e((string) ($index + 1)) ?>">
            <img src="<?= e($site->asset($shot)) ?>" alt="" loading="lazy">
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <div class="product__marks">
        <?php foreach ($block['marks'] ?? [] as $index => $mark): ?>
          <span class="mark mark--<?= e($mark['style'] ?? 'outline') ?>"<?= $view->editable($block, "marks.$index.label") ?>><?= e($mark['label']) ?></span>
        <?php endforeach; ?>
      </div>

      <h1 class="product__title" data-hero-in style="--d: .06s"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h1>
      <p class="product__lead" data-hero-in style="--d: .13s"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

      <?php if ($offer): ?>
        <div class="offer">
          <div class="offer__head">
            <span class="offer__price"<?= $view->editable($block, 'offer.price') ?>><?= e($offer['price']) ?></span>
            <span class="offer__note"<?= $view->editable($block, 'offer.note') ?>><?= e($offer['note']) ?></span>
          </div>
          <div class="offer__rule"></div>

          <div class="offer__list">
            <?php foreach ($offer['points'] as $pointIndex => $point): ?>
              <div><span class="offer__arrow">→</span><span<?= $view->editable($block, "offer.points.$pointIndex") ?>><?= e($point) ?></span></div>
            <?php endforeach; ?>
          </div>

          <div class="offer__actions">
            <?php foreach ($offer['actions'] as $actionIndex => $action): ?>
              <?= $view->partial('action', ['action' => $action, 'edit' => $view->editable($block, "offer.actions.$actionIndex.label")]) ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($block['quickspecs'])): ?>
        <div class="quickspecs">
          <?php foreach ($block['quickspecs'] as $quickIndex => $spec): ?>
            <div class="quickspecs__item">
              <div class="quickspecs__value"<?= $view->editable($block, "quickspecs.$quickIndex.value") ?>><?= e($spec['value']) ?></div>
              <div class="quickspecs__caption"<?= $view->editable($block, "quickspecs.$quickIndex.caption") ?>><?= e($spec['caption']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

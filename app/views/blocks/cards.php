<?php
declare(strict_types=1);

/**
 * Универсальный блок: заголовок, сетка карточек и необязательные кнопки.
 * Им описаны секции «Запчасти», «Магазин», «Документы» и «Доставка».
 *
 * @var Site  $site
 * @var View  $view
 * @var array $block
 */

$panel = !empty($block['panel']);
$cellClass = classes(['cell', 'cell--2' => ($block['surface'] ?? '') === 'surface-2', 'cell--tall' => !empty($block['tall'])]);
$gridStyle = '--min: ' . (int) ($block['min'] ?? 260) . 'px'
    . (!empty($block['narrow']) ? '; max-width: 860px' : '')
    . (!empty($block['maxw']) ? '; max-width: ' . (int) $block['maxw'] . 'px' : '')
    . (!empty($block['actions']) ? '; margin-bottom: 32px' : '');

$body = static function () use ($site, $block, $view, $cellClass, $gridStyle): void { ?>
  <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
  <h2 class="h2" style="max-width: 24ch"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
  <?php if (!empty($block['lead'])): ?>
    <p class="lead" style="max-width: 56ch"<?= $view->editable($block, 'lead') ?>><?= e($block['lead']) ?></p>
  <?php endif; ?>

  <div class="grid-lines" style="<?= e($gridStyle) ?>">
    <?php foreach ($block['items'] as $index => $item): ?>
      <?php
      // Карточка со ссылкой кликается целиком — так удобнее на телефоне
      $link = (string) ($item['url'] ?? '');
      $tag = $link !== '' ? 'a' : 'div';
      $href = $link !== '' ? ' href="' . e(preg_match('~^([a-z]+:|/|#)~i', $link) ? $link : $site->url($link)) . '"' : '';
      ?>
      <<?= $tag ?><?= $href ?> class="<?= e($cellClass) ?><?= $link !== '' ? ' cell--link' : '' ?>">
        <?php if (isset($item['label'])): ?>
          <?php /* карточка-метка: заголовок набран как рубрика, а не как название */ ?>
          <div class="cell__label"<?= $view->editable($block, "items.$index.label") ?>><?= e($item['label']) ?></div>
          <p class="text" style="color: var(--text-2)"<?= $view->editable($block, "items.$index.text") ?>><?= e($item['text']) ?></p>
        <?php else: ?>
          <h3 class="h3"<?= $view->editable($block, "items.$index.title") ?>><?= e($item['title']) ?></h3>
          <p class="text"<?= $view->editable($block, "items.$index.text") ?>><?= e($item['text']) ?></p>
        <?php endif; ?>

        <?php if ($link !== ''): ?>
          <span class="cell__arrow">→</span>
        <?php endif; ?>
      </<?= $tag ?>>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($block['actions'])): ?>
    <div class="btn-row btn-row--tight">
      <?php foreach ($block['actions'] as $index => $action): ?>
        <?= $view->partial('action', [
            'action' => $action,
            'edit'   => $view->editable($block, "actions.$index.label"),
        ]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php };
?>
<?php if ($panel): ?>
  <section id="<?= e($block['id'] ?? '') ?>" class="band">
    <div class="wrap section"><?php $body(); ?></div>
  </section>
<?php else: ?>
  <section id="<?= e($block['id'] ?? '') ?>" class="wrap section"><?php $body(); ?></section>
<?php endif; ?>

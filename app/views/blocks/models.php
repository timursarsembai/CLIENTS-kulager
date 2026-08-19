<?php
declare(strict_types=1);

/** @var Site $site @var View $view @var array $block */
?>
<section id="<?= e($block['id'] ?? 'models') ?>" class="wrap section">
  <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
  <h2 class="h2" style="margin-bottom: 36px"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>

  <div class="grid-gap" style="--min: 340px">
    <?php foreach ($block['items'] as $index => $model): ?>
      <article class="model<?= !empty($model['featured']) ? ' model--featured' : '' ?>">
        <img src="<?= e($site->asset($model['image'])) ?>" alt="<?= e($model['alt']) ?>">
        <div class="model__body">
          <div class="model__head">
            <h3 class="model__name"><a href="<?= e($site->url($model['url'])) ?>"<?= $view->editable($block, "items.$index.title") ?>><?= e($model['title']) ?></a></h3>
            <?php foreach ($model['badges'] as $badgeIndex => $badge): ?>
              <span class="badge badge--<?= e($badge['style']) ?>"<?= $view->editable($block, "items.$index.badges.$badgeIndex.label") ?>><?= e($badge['label']) ?></span>
            <?php endforeach; ?>
          </div>

          <table class="spec-table">
            <tbody>
              <?php foreach ($model['specs'] as $specIndex => $spec): ?>
                <tr>
                  <th scope="row"<?= $view->editable($block, "items.$index.specs.$specIndex.name") ?>><?= e($spec['name']) ?></th>
                  <td<?= $view->editable($block, "items.$index.specs.$specIndex.value") ?>><?= e($spec['value']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="btn-row btn-row--tight" style="margin-top: 22px">
            <?php foreach ($model['actions'] as $actionIndex => $action): ?>
              <?= $view->partial('action', ['action' => $action + ['size' => 'sm'], 'edit' => $view->editable($block, "items.$index.actions.$actionIndex.label")]) ?>
            <?php endforeach; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($block['note'])): ?>
    <p class="uppercase-note"<?= $view->editable($block, 'note') ?>><?= e($block['note']) ?></p>
  <?php endif; ?>
</section>

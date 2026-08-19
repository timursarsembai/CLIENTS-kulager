<?php
declare(strict_types=1);

/**
 * Таблица «параметр — значение». Используется для полных характеристик
 * модели и для реквизитов организации.
 *
 * @var Site  $site
 * @var View  $view
 * @var array $block
 */

$hasImage = !empty($block['image']);
?>
<section id="<?= e($block['id'] ?? 'specs') ?>" class="wrap section">
  <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
  <h2 class="h2" style="max-width: 22ch"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
  <?php if (!empty($block['lead'])): ?>
    <p class="lead" style="max-width: 54ch"<?= $view->editable($block, 'lead') ?>><?= e($block['lead']) ?></p>
  <?php endif; ?>

  <div<?= $hasImage ? ' class="split" style="--min: 320px; align-items: start"' : '' ?>>
    <div class="table-wrap"<?= $hasImage ? '' : ' style="max-width: 900px; margin-top: 24px"' ?>>
      <table class="data-table data-table--<?= $hasImage ? 'specs' : 'kv' ?>">
        <tbody>
          <?php foreach ($block['rows'] as $index => $row): ?>
            <tr>
              <th scope="row"<?= $view->editable($block, "rows.$index.name") ?>><?= e($row['name']) ?></th>
              <td<?= $view->editable($block, "rows.$index.value") ?>><?= e($row['value']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($hasImage): ?>
      <img src="<?= e($site->asset($block['image'])) ?>" alt="<?= e($block['alt'] ?? '') ?>" style="width: 100%; background: #fff; border: 1px solid var(--line)">
    <?php endif; ?>
  </div>

  <?php if (!empty($block['note'])): ?>
    <p style="font-size: 14px; color: var(--text-4); margin: 16px 0 0"<?= $view->editable($block, 'note') ?>><?= e($block['note']) ?></p>
  <?php endif; ?>
</section>

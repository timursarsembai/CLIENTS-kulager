<?php
declare(strict_types=1);

/**
 * Текст и таблица «параметр — MC1 — MC1e» рядом с фотографией.
 *
 * @var Site  $site
 * @var View  $view
 * @var array $block
 */
?>
<section id="<?= e($block['id'] ?? 'why') ?>" class="wrap section">
  <div class="split split--stretch">
    <div>
      <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></div>
      <h2 class="h2" style="max-width: 24ch"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>
      <p class="lead" style="max-width: 56ch"<?= $view->editable($block, 'lead') ?>><?= e($block['lead'] ?? '') ?></p>

      <div style="border: 1px solid var(--line)">
        <table class="data-table">
          <thead>
            <tr>
              <th scope="col" style="width: 34%"<?= $view->editable($block, 'columns.0') ?>><?= e($block['columns'][0]) ?></th>
              <th scope="col" class="is-primary"<?= $view->editable($block, 'columns.1') ?>><?= e($block['columns'][1]) ?></th>
              <th scope="col"<?= $view->editable($block, 'columns.2') ?>><?= e($block['columns'][2]) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($block['rows'] as $index => $row): ?>
              <tr>
                <th scope="row"<?= $view->editable($block, "rows.$index.name") ?>><?= e($row['name']) ?></th>
                <td class="is-primary"<?= $view->editable($block, "rows.$index.a") ?>><?= e($row['a']) ?></td>
                <td<?= $view->editable($block, "rows.$index.b") ?>><?= e($row['b']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (!empty($block['footnote'])): ?>
          <p class="table-foot"<?= $view->editable($block, 'footnote', 'html') ?>><?= $block['footnote'] ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="split__media">
      <img src="<?= e($site->asset($block['image'])) ?>" alt="<?= e($block['alt'] ?? '') ?>">
    </div>
  </div>
</section>

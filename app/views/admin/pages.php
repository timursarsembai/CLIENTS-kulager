<?php
declare(strict_types=1);

/** @var Site $site @var Admin $admin @var array $pages @var array $locales */

$sections = PageRepository::sections();
?>
<h1 class="page-title">Страницы</h1>

<?php if ($pages === []): ?>
  <div class="card">
    <h2 class="card__title">Страниц пока нет</h2>
    <p class="card__lead">Перенесите текущий контент из файлов — это делается одной кнопкой.</p>
    <a href="<?= e($admin->url('system')) ?>" class="btn">Перейти к переносу</a>
  </div>
<?php else: ?>

  <?php
  $grouped = [];
  foreach ($pages as $page) {
      $grouped[$page['section']][] = $page;
  }
  ?>

  <?php foreach ($grouped as $section => $items): ?>
    <div class="card">
      <h2 class="card__title"><?= e($sections[$section] ?? $section) ?></h2>

      <table class="table table--pages">
        <thead>
          <tr>
            <th>Страница</th>
            <?php foreach ($locales as $code => $meta): ?>
              <th class="nowrap"><?= e($meta['short']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $page): ?>
          <tr>
            <td>
              <a href="<?= e($admin->url('page/' . $page['id'] . '/' . $site->defaultLocale())) ?>">
                <strong><?= e($page['locales'][$site->defaultLocale()]['title'] ?? $page['page_key']) ?></strong>
              </a>
              <div class="muted"><?= e($page['page_key']) ?></div>
            </td>

            <?php foreach ($locales as $code => $meta): ?>
              <?php $row = $page['locales'][$code] ?? null; ?>
              <td>
                <a href="<?= e($admin->url('page/' . $page['id'] . '/' . $code)) ?>" class="locale-cell">
                  <?php if ($row === null): ?>
                    <span class="muted">не заполнена</span>
                  <?php else: ?>
                    <span class="pill pill--<?= $row['is_published'] ? 'ok' : 'draft' ?>">
                      <?= $row['is_published'] ? 'опубликована' : 'черновик' ?>
                    </span>
                    <span class="muted nowrap"><?= e((string) $row['blocks_count']) ?> блоков</span>
                  <?php endif; ?>
                </a>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>

<?php endif; ?>

<?php
declare(strict_types=1);

/**
 * Список меню сайта. Правится каждое меню отдельно и на своём языке.
 *
 * @var Admin  $admin
 * @var string $locale
 * @var array  $locales
 * @var array  $menus
 * @var array  $counts
 * @var bool   $imported
 */
?>
<h1 class="page-title"><?= ate('Меню') ?></h1>

<?php if (!$imported): ?>
  <div class="notice notice--warn">
    Меню ещё не перенесено в базу — сайт показывает его из файлов
    <code>content/navigation.{язык}.php</code>. Нажмите «Перенести контент»
    в разделе «Состояние»: меню перенесётся вместе со страницами.
  </div>
<?php endif; ?>

<div class="btn-row">
  <?php foreach ($locales as $code => $lang): ?>
    <a href="<?= e($admin->url('menu/' . $code)) ?>"
       class="btn<?= $code === $locale ? ' btn--primary' : '' ?>"><?= e((string) $lang['name']) ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th><?= ate('Меню') ?></th>
        <th><?= ate('Пунктов') ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($menus as $key => $title): ?>
        <tr>
          <td><?= e($title) ?></td>
          <td><?= e((string) ($counts[$key] ?? 0)) ?></td>
          <td class="table__actions">
            <a href="<?= e($admin->url('menu/' . $locale . '/' . $key)) ?>" class="btn btn--small">Править</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<p class="muted"><?= ate('Меню общее для шапки, бокового меню и подвала. Пункт, спрятанный из бокового меню, остаётся в подвале — это отдельный флажок в каждом пункте.') ?></p>

<?php
declare(strict_types=1);

/** @var Admin $admin @var array $stats @var array $recent */

$labels = [
    'setup'   => 'первый запуск',
    'login'   => 'вход',
    'logout'  => 'выход',
    'import'  => 'импорт контента',
    'publish' => 'публикация',
];
?>
<h1 class="page-title">Панель</h1>

<div class="tiles">
  <div class="tile"><span class="tile__value"><?= e((string) $stats['pages']) ?></span><span class="tile__label">страниц</span></div>
  <div class="tile"><span class="tile__value"><?= e((string) $stats['published']) ?></span><span class="tile__label">опубликовано</span></div>
  <div class="tile"><span class="tile__value"><?= e((string) $stats['blocks']) ?></span><span class="tile__label">блоков</span></div>
  <div class="tile"><span class="tile__value"><?= e((string) $stats['media']) ?></span><span class="tile__label">файлов</span></div>
</div>

<?php if ($stats['pages'] === 0): ?>
  <div class="card">
    <h2 class="card__title">Контент ещё не перенесён</h2>
    <p class="card__lead">
      Сайт сейчас работает на файлах <code>app/content</code>. Перенесите их в базу —
      после этого страницы можно будет править через админку.
    </p>
    <a href="<?= e($admin->url('system')) ?>" class="btn">Перейти к переносу</a>
  </div>
<?php endif; ?>

<div class="card">
  <h2 class="card__title">Последние действия</h2>

  <?php if ($recent === []): ?>
    <p class="muted">Пока пусто.</p>
  <?php else: ?>
    <table class="table">
      <tbody>
      <?php foreach ($recent as $row): ?>
        <tr>
          <td class="muted nowrap"><?= e(date('d.m.Y H:i', strtotime((string) $row['created_at']))) ?></td>
          <td><?= e($labels[$row['action']] ?? $row['action']) ?></td>
          <td class="muted"><?= e((string) ($row['user_name'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php
declare(strict_types=1);

/**
 * @var Admin $admin
 * @var list<array> $rows
 * @var int $page
 * @var int $total
 * @var int $perPage
 */

$labels = [
    'setup'           => 'первый запуск',
    'login'           => 'вход',
    'logout'          => 'выход',
    'import'          => 'перенос контента из файлов',
    'publish'         => 'публикация',
    'unpublish'       => 'снятие с публикации',
    'copy_blocks'     => 'копирование блоков между языками',
    'media_upload'    => 'загрузка файлов',
    'media_delete'    => 'удаление файла',
    'settings'        => 'изменение настроек',
    'password_change' => 'смена пароля',
    'backup'          => 'выгрузка резервной копии',
];

$pages = (int) ceil($total / $perPage);
?>
<h1 class="page-title">Журнал действий</h1>

<div class="card">
  <?php if ($rows === []): ?>
    <p class="muted">Пока пусто.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Когда</th><th>Что</th><th>Где</th><th>Кто</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="muted nowrap"><?= e(date('d.m.Y H:i', strtotime((string) $row['created_at']))) ?></td>
          <td><?= e($labels[$row['action']] ?? (string) $row['action']) ?></td>
          <td class="muted"><?= e((string) $row['target']) ?></td>
          <td class="muted"><?= e((string) ($row['user_name'] ?: $row['user_email'] ?: '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($pages > 1): ?>
      <div class="pager">
        <?php if ($page > 1): ?>
          <a href="<?= e($admin->url('log') . '?p=' . ($page - 1)) ?>" class="btn btn--small">Назад</a>
        <?php endif; ?>

        <span class="muted">Страница <?= e((string) $page) ?> из <?= e((string) $pages) ?></span>

        <?php if ($page < $pages): ?>
          <a href="<?= e($admin->url('log') . '?p=' . ($page + 1)) ?>" class="btn btn--small">Дальше</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

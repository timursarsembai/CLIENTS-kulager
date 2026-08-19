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
    'setup'           => at('первый запуск'),
    'login'           => at('вход'),
    'logout'          => at('выход'),
    'import'          => at('перенос контента из файлов'),
    'publish'         => at('публикация'),
    'unpublish'       => at('снятие с публикации'),
    'copy_blocks'     => at('копирование блоков между языками'),
    'media_upload'    => at('загрузка файлов'),
    'media_delete'    => at('удаление файла'),
    'settings'        => at('изменение настроек'),
    'password_change' => at('смена пароля'),
    'backup'          => at('выгрузка резервной копии'),
];

$pages = (int) ceil($total / $perPage);
?>
<h1 class="page-title"><?= ate('Журнал действий') ?></h1>

<div class="card">
  <?php if ($rows === []): ?>
    <p class="muted"><?= ate('Пока пусто.') ?></p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th><?= ate('Когда') ?></th><th><?= ate('Что') ?></th><th><?= ate('Где') ?></th><th><?= ate('Кто') ?></th></tr>
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
          <a href="<?= e($admin->url('log') . '?p=' . ($page - 1)) ?>" class="btn btn--small"><?= ate('Назад') ?></a>
        <?php endif; ?>

        <span class="muted"><?= ate('Страница %d из %d', $page, $pages) ?></span>

        <?php if ($page < $pages): ?>
          <a href="<?= e($admin->url('log') . '?p=' . ($page + 1)) ?>" class="btn btn--small"><?= ate('Дальше') ?></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

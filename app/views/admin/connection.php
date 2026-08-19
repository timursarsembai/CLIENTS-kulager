<?php
declare(strict_types=1);

/** @var bool $configured */
?>
<div class="card card--form">
  <h1 class="card__title"><?= ate('Нет связи с базой данных') ?></h1>

  <?php if (!$configured): ?>
    <p class="card__lead">
      <?= at('Подключение не настроено. Укажите доступы в %s, раздел %s: строку подключения, пользователя и пароль.',
              '<code>app/config.php</code>', '<code>db</code>') ?>
    </p>
  <?php else: ?>
    <p class="card__lead">
      <?= at('Доступы указаны, но подключиться не удалось. Проверьте имя базы, логин и пароль в панели хостинга. Подробности ошибки — в журнале %s.',
              '<code>app/logs/php-error.log</code>') ?>
    </p>
  <?php endif; ?>

  <p class="muted"><?= ate('Публичная часть сайта при этом работает: страницы отдаются из файлов.') ?></p>
</div>

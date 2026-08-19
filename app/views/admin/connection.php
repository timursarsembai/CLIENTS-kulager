<?php
declare(strict_types=1);

/** @var bool $configured */
?>
<div class="card card--form">
  <h1 class="card__title"><?= ate('Нет связи с базой данных') ?></h1>

  <?php if (!$configured): ?>
    <p class="card__lead">
      Подключение не настроено. Укажите доступы в <code>app/config.php</code>,
      раздел <code>db</code>: строку подключения, пользователя и пароль.
    </p>
  <?php else: ?>
    <p class="card__lead">
      Доступы указаны, но подключиться не удалось. Проверьте имя базы, логин и пароль
      в панели хостинга. Подробности ошибки — в журнале <code>app/logs/php-error.log</code>.
    </p>
  <?php endif; ?>

  <p class="muted"><?= ate('Публичная часть сайта при этом работает: страницы отдаются из файлов.') ?></p>
</div>

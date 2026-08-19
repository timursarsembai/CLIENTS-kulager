<?php
declare(strict_types=1);

/** @var Admin $admin @var array $pending */
?>
<div class="card card--form">
  <h1 class="card__title"><?= ate('Нужно обновить базу') ?></h1>
  <p class="card__lead"><?= ate('Структура базы отстаёт от кода. Это безопасная операция: миграции только добавляют таблицы и поля.') ?></p>

  <ul class="list">
    <?php foreach ($pending as $name): ?>
      <li><code><?= e($name) ?></code></li>
    <?php endforeach; ?>
  </ul>

  <form method="post" action="<?= e($admin->url('migrate')) ?>">
    <?= Csrf::field() ?>
    <button type="submit" class="btn btn--primary"><?= ate('Применить миграции') ?></button>
  </form>
</div>

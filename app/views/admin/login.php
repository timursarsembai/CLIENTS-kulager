<?php
declare(strict_types=1);

/** @var Admin $admin @var ?string $error @var string $email */
?>
<div class="card card--form">
  <h1 class="card__title">Вход в админку</h1>

  <?php if ($error): ?>
    <div class="notice notice--error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e($admin->url('login')) ?>">
    <?= Csrf::field() ?>

    <label class="field">
      <span class="field__label">Почта</span>
      <input type="email" name="email" value="<?= e((string) $email) ?>" required autofocus autocomplete="username">
    </label>

    <label class="field">
      <span class="field__label">Пароль</span>
      <input type="password" name="password" required autocomplete="current-password">
    </label>

    <button type="submit" class="btn btn--primary">Войти</button>
  </form>
</div>

<?php
declare(strict_types=1);

/** @var Admin $admin @var array $errors @var array $input */
?>
<div class="card card--form">
  <h1 class="card__title">Первый запуск</h1>
  <p class="card__lead">Создайте учётную запись администратора. Пока её нет, в админку не попасть.</p>

  <form method="post" action="<?= e($admin->url('setup')) ?>">
    <?= Csrf::field() ?>

    <label class="field">
      <span class="field__label">Имя</span>
      <input type="text" name="name" value="<?= e((string) ($input['name'] ?? '')) ?>" autocomplete="name">
    </label>

    <label class="field<?= isset($errors['email']) ? ' field--error' : '' ?>">
      <span class="field__label">Почта</span>
      <input type="email" name="email" value="<?= e((string) ($input['email'] ?? '')) ?>" required autocomplete="username">
      <?php if (isset($errors['email'])): ?><span class="field__error"><?= e($errors['email']) ?></span><?php endif; ?>
    </label>

    <label class="field<?= isset($errors['password']) ? ' field--error' : '' ?>">
      <span class="field__label">Пароль</span>
      <input type="password" name="password" required autocomplete="new-password">
      <span class="field__hint">Не короче 10 символов.</span>
      <?php if (isset($errors['password'])): ?><span class="field__error"><?= e($errors['password']) ?></span><?php endif; ?>
    </label>

    <label class="field<?= isset($errors['password_confirm']) ? ' field--error' : '' ?>">
      <span class="field__label">Пароль ещё раз</span>
      <input type="password" name="password_confirm" required autocomplete="new-password">
      <?php if (isset($errors['password_confirm'])): ?><span class="field__error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
    </label>

    <button type="submit" class="btn btn--primary">Создать и войти</button>
  </form>
</div>

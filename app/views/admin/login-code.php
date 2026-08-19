<?php
declare(strict_types=1);

/**
 * Второй шаг входа: пароль уже принят, ждём одноразовый код.
 *
 * @var Admin   $admin
 * @var ?string $error
 */
?>
<div class="card card--form">
  <h1 class="card__title">Код подтверждения</h1>
  <p class="card__lead">
    Откройте приложение-аутентификатор и введите шестизначный код для KULAGER.
    Вместо него подойдёт любой из запасных кодов.
  </p>

  <?php if ($error): ?>
    <div class="notice notice--error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e($admin->url('login')) ?>">
    <?= Csrf::field() ?>

    <label class="field">
      <span class="field__label">Код</span>
      <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
             pattern="[0-9A-Za-z ]{6,20}" required autofocus>
    </label>

    <button type="submit" class="btn btn--primary">Подтвердить</button>
  </form>
</div>

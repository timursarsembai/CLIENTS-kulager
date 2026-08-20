<?php
declare(strict_types=1);

/**
 * Восстановление пароля через телеграм: два шага в одной форме.
 *
 * Первый шаг спрашивает почту, второй — код из телеграма и новый пароль.
 * О том, заведена ли такая почта, страница не говорит: ответ одинаковый
 * в обоих случаях, иначе форма превращается в способ узнать, кто здесь есть.
 *
 * @var Admin   $admin
 * @var string  $step  email или code
 * @var string  $email
 * @var ?string $error
 * @var bool    $sent
 */
?>
<div class="card card--form">
  <h1 class="card__title"><?= ate('Восстановление пароля') ?></h1>

  <?php if ($step === 'email'): ?>
    <p class="card__lead"><?= ate('Код придёт в телеграм — в тот же чат, куда падают заявки с сайта.') ?></p>
  <?php else: ?>
    <p class="card__lead"><?= ate('Если такая почта заведена, код уже в телеграме. Он годен 15 минут.') ?></p>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="notice notice--error"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($step === 'email'): ?>
    <form method="post" action="<?= e($admin->url('vosstanovlenie')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="step" value="email">

      <label class="field">
        <span class="field__label"><?= ate('Почта') ?></span>
        <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
      </label>

      <button type="submit" class="btn btn--primary"><?= ate('Прислать код') ?></button>
    </form>
  <?php else: ?>
    <form method="post" action="<?= e($admin->url('vosstanovlenie')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="step" value="code">
      <input type="hidden" name="email" value="<?= e($email) ?>">

      <label class="field">
        <span class="field__label"><?= ate('Код из телеграма') ?></span>
        <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
               required autofocus autocomplete="one-time-code">
      </label>

      <label class="field">
        <span class="field__label"><?= ate('Новый пароль') ?></span>
        <input type="password" name="password" required autocomplete="new-password">
        <span class="field__hint"><?= ate('Не короче 10 символов.') ?></span>
      </label>

      <label class="field">
        <span class="field__label"><?= ate('Новый пароль ещё раз') ?></span>
        <input type="password" name="password_confirm" required autocomplete="new-password">
      </label>

      <button type="submit" class="btn btn--primary"><?= ate('Сменить пароль') ?></button>
    </form>

    <p class="gate__aside">
      <a href="<?= e($admin->url('vosstanovlenie')) ?>"><?= ate('Прислать код заново') ?></a>
    </p>
  <?php endif; ?>

  <p class="gate__aside">
    <a href="<?= e($admin->url('login')) ?>"><?= ate('Вспомнил пароль — войти') ?></a>
  </p>
</div>

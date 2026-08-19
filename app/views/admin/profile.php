<?php
declare(strict_types=1);

/**
 * @var Admin  $admin
 * @var Auth   $auth
 * @var array  $errors
 * @var string $secret новый ключ для приложения — показывается один раз
 * @var array  $codes  запасные коды — тоже один раз
 */

$user = $auth->user();
$twofa = !empty($user['totp_enabled']);
?>
<h1 class="page-title"><?= ate('Профиль') ?></h1>

<div class="card card--form">
  <h2 class="card__title"><?= ate('Смена пароля') ?></h2>
  <p class="card__lead">
    <?= e($user['email']) ?> — <?= $auth->isAdmin() ? ate('администратор') : ate('редактор') ?>.
  </p>

  <form method="post" action="<?= e($admin->url('profile')) ?>">
    <?= Csrf::field() ?>

    <label class="field<?= isset($errors['current']) ? ' field--error' : '' ?>">
      <span class="field__label"><?= ate('Текущий пароль') ?></span>
      <input type="password" name="current" required autocomplete="current-password">
      <?php if (isset($errors['current'])): ?><span class="field__error"><?= e($errors['current']) ?></span><?php endif; ?>
    </label>

    <label class="field<?= isset($errors['password']) ? ' field--error' : '' ?>">
      <span class="field__label"><?= ate('Новый пароль') ?></span>
      <input type="password" name="password" required autocomplete="new-password">
      <span class="field__hint"><?= ate('Не короче 10 символов.') ?></span>
      <?php if (isset($errors['password'])): ?><span class="field__error"><?= e($errors['password']) ?></span><?php endif; ?>
    </label>

    <label class="field<?= isset($errors['password_confirm']) ? ' field--error' : '' ?>">
      <span class="field__label"><?= ate('Новый пароль ещё раз') ?></span>
      <input type="password" name="password_confirm" required autocomplete="new-password">
      <?php if (isset($errors['password_confirm'])): ?><span class="field__error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
    </label>

    <button type="submit" class="btn btn--primary"><?= ate('Сменить пароль') ?></button>
  </form>
</div>

<div class="card card--form">
  <h2 class="card__title"><?= ate('Вход по одноразовому коду') ?></h2>

  <p class="card__lead">
    <?= ate('Второй шаг входа: после пароля админка спросит шестизначный код из приложения-аутентификатора. Пароль, подсмотренный или подобранный, сам по себе перестаёт открывать доступ.') ?>
    <?php if ($twofa): ?>
      <?= at('Сейчас %s.', '<strong>' . ate('включён') . '</strong>') ?>
    <?php else: ?>
      <?= ate('Сейчас выключен.') ?>
    <?php endif; ?>
  </p>

  <?php if ($codes !== []): ?>
    <div class="notice notice--warn">
      <strong><?= ate('Запасные коды.') ?></strong>
      <?= ate('Каждый срабатывает один раз — понадобятся, если телефон потеряется. Сохраните их сейчас: снова показать нельзя.') ?>
      <div class="backup-codes">
        <?php foreach ($codes as $code): ?><code><?= e($code) ?></code><?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($secret !== ''): ?>
    <div class="notice notice--ok">
      <p><?= ate('Добавьте ключ в приложение (Google Authenticator, Aegis, 1Password): вручную или по ссылке ниже. Затем введите код — второй шаг включится.') ?></p>

      <p class="backup-codes"><code><?= e(Totp::readable($secret)) ?></code></p>

      <p>
        <a href="<?= e(Totp::uri($secret, (string) $user['email'], 'KULAGER')) ?>">
          <?= ate('Открыть в приложении на этом устройстве') ?>
        </a>
      </p>

      <form method="post" action="<?= e($admin->url('twofa')) ?>" class="menu-add__body">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="enable">
        <input type="text" name="code" inputmode="numeric" placeholder="<?= ate('Код из приложения') ?>" required>
        <button type="submit" class="btn btn--primary"><?= ate('Включить') ?></button>
      </form>
    </div>
  <?php elseif (!$twofa): ?>
    <form method="post" action="<?= e($admin->url('twofa')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="start">
      <button type="submit" class="btn btn--primary"><?= ate('Настроить') ?></button>
    </form>
  <?php else: ?>
    <div class="btn-row">
      <form method="post" action="<?= e($admin->url('twofa')) ?>"
            data-confirm="<?= ate('Перевыпустить запасные коды? Прежние перестанут действовать.') ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="codes">
        <button type="submit" class="btn"><?= ate('Перевыпустить запасные коды') ?></button>
      </form>
    </div>

    <form method="post" action="<?= e($admin->url('twofa')) ?>" class="menu-add__body"
          data-confirm="<?= ate('Отключить вход по одноразовому коду?') ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="disable">
      <input type="password" name="current" placeholder="<?= ate('Текущий пароль') ?>" required autocomplete="current-password">
      <button type="submit" class="btn btn--danger"><?= ate('Отключить') ?></button>
    </form>
  <?php endif; ?>
</div>

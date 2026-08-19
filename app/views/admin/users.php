<?php
declare(strict_types=1);

/**
 * Пользователи админки: кто есть, с какой ролью, кому закрыт доступ.
 *
 * @var Admin $admin
 * @var list<array> $users
 * @var int   $me      идентификатор того, кто смотрит
 * @var array $created почта и пароль только что заведённого — показываются раз
 */
?>
<h1 class="page-title"><?= ate('Пользователи') ?></h1>

<p class="lead-text"><?= ate('Администратор меняет всё, включая настройки сайта, перенос контента и эти самые учётные записи. Редактор работает только с контентом: страницы, блоки, меню, медиатека.') ?></p>

<?php if ($created !== []): ?>
  <div class="notice notice--warn">
    <strong>Пароль для <?= e((string) $created['email']) ?>:</strong>
    <span class="backup-codes"><code><?= e((string) $created['password']) ?></code></span>
    Передайте его лично — здесь он больше не покажется. При первом входе
    пусть сменит его в разделе «Профиль».
  </div>
<?php endif; ?>

<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th><?= ate('Почта') ?></th>
        <th><?= ate('Роль') ?></th>
        <th><?= ate('Вход') ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <?php
        $id = (int) $user['id'];
        $isMe = $id === $me;
        $active = !empty($user['is_active']);
        ?>
        <tr>
          <td>
            <?= e((string) $user['email']) ?>
            <?php if ($isMe): ?><span class="pill pill--ok"><?= ate('это вы') ?></span><?php endif; ?>
            <?php if (!$active): ?><span class="pill pill--draft"><?= ate('доступ закрыт') ?></span><?php endif; ?>

            <?php if (($user['name'] ?? '') !== '' && $user['name'] !== $user['email']): ?>
              <div class="muted"><?= e((string) $user['name']) ?></div>
            <?php endif; ?>
          </td>

          <td>
            <form method="post" action="<?= e($admin->url('users/' . $id . '/role')) ?>" class="menu-add__body">
              <?= Csrf::field() ?>
              <select name="role">
                <option value="editor"<?= $user['role'] === 'editor' ? ' selected' : '' ?>>Редактор</option>
                <option value="admin"<?= $user['role'] === 'admin' ? ' selected' : '' ?>>Администратор</option>
              </select>
              <button type="submit" class="btn btn--small"><?= ate('Сменить') ?></button>
            </form>
          </td>

          <td class="nowrap">
            <?php if (($user['last_login_at'] ?? null) !== null): ?>
              <?= e(date('d.m.Y H:i', strtotime((string) $user['last_login_at']))) ?>
            <?php else: ?>
              <span class="muted"><?= ate('ни разу') ?></span>
            <?php endif; ?>

            <?php if (!empty($user['totp_enabled'])): ?>
              <div><span class="pill pill--ok"><?= ate('вход по коду') ?></span></div>
            <?php endif; ?>
          </td>

          <td class="table__actions">
            <form method="post" action="<?= e($admin->url('users/' . $id . '/password')) ?>" class="inline-form"
                  data-confirm="Сбросить пароль? Нынешний перестанет работать.">
              <?= Csrf::field() ?>
              <button type="submit" class="link"><?= ate('Сбросить пароль') ?></button>
            </form>

            <?php if (!empty($user['totp_enabled'])): ?>
              <form method="post" action="<?= e($admin->url('users/' . $id . '/twofa')) ?>" class="inline-form"
                    data-confirm="Отключить вход по коду? Понадобится, если человек потерял телефон.">
                <?= Csrf::field() ?>
                <button type="submit" class="link"><?= ate('Сбросить код') ?></button>
              </form>
            <?php endif; ?>

            <?php if (!$isMe): ?>
              <form method="post" action="<?= e($admin->url('users/' . $id . '/toggle')) ?>" class="inline-form">
                <?= Csrf::field() ?>
                <button type="submit" class="link"><?= $active ? 'Закрыть доступ' : 'Открыть доступ' ?></button>
              </form>

              <form method="post" action="<?= e($admin->url('users/' . $id . '/delete')) ?>" class="inline-form"
                    data-confirm="Удалить пользователя? Его записи в журнале останутся.">
                <?= Csrf::field() ?>
                <button type="submit" class="link link--danger"><?= ate('Удалить') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2 class="card__title"><?= ate('Новый пользователь') ?></h2>
  <p class="card__lead"><?= ate('Пароль придумает система — он покажется один раз, сразу после создания. Почта служит логином.') ?></p>

  <form method="post" action="<?= e($admin->url('users/add')) ?>" class="menu-add__body">
    <?= Csrf::field() ?>

    <input type="email" name="email" placeholder="Почта" required>
    <input type="text" name="name" placeholder="Имя (необязательно)">

    <select name="role">
      <option value="editor"><?= ate('Редактор') ?></option>
      <option value="admin"><?= ate('Администратор') ?></option>
    </select>

    <button type="submit" class="btn btn--primary"><?= ate('Завести') ?></button>
  </form>
</div>

<?php
declare(strict_types=1);

/**
 * Заявки с сайта и настройка уведомлений в телеграм.
 *
 * @var Admin $admin
 * @var list<array> $leads
 * @var string $status
 * @var array $counts
 * @var array $telegram
 * @var bool  $isAdmin
 */

$labels = [
    'new'     => 'Новые',
    'in_work' => 'В работе',
    'done'    => 'Обработаны',
    'spam'    => 'Спам',
];
?>
<h1 class="page-title">Заявки</h1>

<?php if ($isAdmin && (!$telegram['token'] || $telegram['chat'] === '')): ?>
  <div class="notice notice--warn">
    Уведомления в телеграм не настроены — заявки будут копиться только здесь.
    Настройка внизу страницы.
  </div>
<?php endif; ?>

<div class="btn-row">
  <a href="<?= e($admin->url('leads')) ?>" class="btn<?= $status === '' ? ' btn--primary' : '' ?>">
    Все
  </a>
  <?php foreach ($labels as $key => $label): ?>
    <a href="<?= e($admin->url('leads') . '?status=' . $key) ?>"
       class="btn<?= $status === $key ? ' btn--primary' : '' ?>">
      <?= e($label) ?> <span class="muted"><?= e((string) ($counts[$key] ?? 0)) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if ($leads === []): ?>
    <p class="muted">Заявок пока нет.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Когда</th>
          <th>Кто</th>
          <th>Сообщение</th>
          <th>Состояние</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leads as $lead): ?>
          <tr>
            <td class="nowrap">
              <?= e(date('d.m.Y H:i', strtotime((string) $lead['created_at']))) ?>
              <div class="muted">/<?= e((string) $lead['page']) ?></div>
            </td>

            <td>
              <strong><?= e((string) $lead['name']) ?></strong>

              <?php if (($lead['phone'] ?? '') !== ''): ?>
                <div><a href="tel:<?= e(preg_replace('~[^\d+]~', '', (string) $lead['phone']) ?? '') ?>"><?= e((string) $lead['phone']) ?></a></div>
              <?php endif; ?>

              <?php if (($lead['email'] ?? '') !== ''): ?>
                <div><a href="mailto:<?= e((string) $lead['email']) ?>"><?= e((string) $lead['email']) ?></a></div>
              <?php endif; ?>
            </td>

            <td>
              <?= nl2br(e((string) ($lead['message'] ?? ''))) ?>

              <?php if (!$lead['notified']): ?>
                <div>
                  <span class="pill pill--warn">не ушло в телеграм</span>
                  <?php if (($lead['notify_error'] ?? '') !== ''): ?>
                    <span class="muted"><?= e((string) $lead['notify_error']) ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>

            <td>
              <form method="post" action="<?= e($admin->url('leads/' . $lead['id'] . '/status')) ?>" class="menu-add__body">
                <?= Csrf::field() ?>
                <select name="status">
                  <?php foreach ($labels as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $lead['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn--small">Сохранить</button>
              </form>
            </td>

            <td class="table__actions">
              <?php if (!$lead['notified']): ?>
                <form method="post" action="<?= e($admin->url('leads/' . $lead['id'] . '/resend')) ?>" class="inline-form">
                  <?= Csrf::field() ?>
                  <button type="submit" class="link">Отправить в телеграм</button>
                </form>
              <?php endif; ?>

              <form method="post" action="<?= e($admin->url('leads/' . $lead['id'] . '/delete')) ?>"
                    class="inline-form" data-confirm="Удалить заявку?">
                <?= Csrf::field() ?>
                <button type="submit" class="link link--danger">Удалить</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
  <div class="card card--form">
    <h2 class="card__title">Уведомления в телеграм</h2>
    <p class="card__lead">
      Заявка сначала сохраняется здесь и только потом уходит в телеграм —
      если бот недоступен, обращение не потеряется, а в списке будет пометка.
    </p>

    <form method="post" action="<?= e($admin->url('leads/telegram')) ?>">
      <?= Csrf::field() ?>

      <label class="field">
        <span class="field__label">Токен бота</span>
        <input type="text" name="telegram_token" value=""
               placeholder="<?= $telegram['token'] ? 'сохранён — оставьте пустым, чтобы не менять' : '123456:AA…' ?>">
        <span class="field__hint">Выдаёт @BotFather. Показывать его здесь мы не будем.</span>
      </label>

      <label class="field">
        <span class="field__label">Куда слать</span>
        <input type="text" name="telegram_chat" value="<?= e((string) $telegram['chat']) ?>" placeholder="123456789">
        <span class="field__hint">
          Идентификатор чата. Напишите боту «/start» (или добавьте его в группу)
          и нажмите «Определить чат».
        </span>
      </label>

      <button type="submit" class="btn btn--primary">Сохранить</button>
    </form>

    <div class="btn-row" style="margin-top: 16px">
      <form method="post" action="<?= e($admin->url('leads/detect')) ?>">
        <?= Csrf::field() ?>
        <button type="submit" class="btn">Определить чат</button>
      </form>

      <form method="post" action="<?= e($admin->url('leads/test')) ?>">
        <?= Csrf::field() ?>
        <button type="submit" class="btn">Отправить проверочное сообщение</button>
      </form>
    </div>
  </div>
<?php endif; ?>

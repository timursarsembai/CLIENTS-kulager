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
    'new'     => at('Новые'),
    'in_work' => at('В работе'),
    'done'    => at('Обработаны'),
    'spam'    => at('Спам'),
];
?>
<h1 class="page-title"><?= ate('Заявки') ?></h1>

<?php if ($isAdmin && (!$telegram['token'] || $telegram['chat'] === '')): ?>
  <div class="notice notice--warn"><?= ate('Уведомления в телеграм не настроены — заявки будут копиться только здесь. Настройка внизу страницы.') ?></div>
<?php endif; ?>

<div class="btn-row">
  <a href="<?= e($admin->url('leads')) ?>" class="btn<?= $status === '' ? ' btn--primary' : '' ?>">
    <?= ate('Все') ?>
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
    <p class="muted"><?= ate('Заявок пока нет.') ?></p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th><?= ate('Когда') ?></th>
          <th><?= ate('Кто') ?></th>
          <th><?= ate('Сообщение') ?></th>
          <th><?= ate('Состояние') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leads as $lead): ?>
          <tr>
            <td class="nowrap">
              <?= e(date('d.m.Y H:i', strtotime((string) $lead['created_at']))) ?>
              <div class="muted">/<?= e((string) $lead['page']) ?></div>

              <?php /* Согласие на обработку данных: подтверждение на случай спора */ ?>
              <?php if (!empty($lead['consent'])): ?>
                <div>
                  <span class="pill pill--ok" title="<?= e((string) ($lead['consent_text'] ?? '')) ?>">
                    <?= ate('согласие есть') ?>
                  </span>
                </div>
              <?php endif; ?>
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
                  <span class="pill pill--warn"><?= ate('не ушло в телеграм') ?></span>
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
                <button type="submit" class="btn btn--small"><?= ate('Сохранить') ?></button>
              </form>
            </td>

            <td class="table__actions">
              <?php if (!$lead['notified']): ?>
                <form method="post" action="<?= e($admin->url('leads/' . $lead['id'] . '/resend')) ?>" class="inline-form">
                  <?= Csrf::field() ?>
                  <button type="submit" class="link"><?= ate('Отправить в телеграм') ?></button>
                </form>
              <?php endif; ?>

              <form method="post" action="<?= e($admin->url('leads/' . $lead['id'] . '/delete')) ?>"
                    class="inline-form" data-confirm="<?= ate('Удалить заявку?') ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="link link--danger"><?= ate('Удалить') ?></button>
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
    <h2 class="card__title"><?= ate('Уведомления в телеграм') ?></h2>
    <p class="card__lead"><?= ate('Заявка сначала сохраняется здесь и только потом уходит в телеграм — если бот недоступен, обращение не потеряется, а в списке будет пометка.') ?></p>

    <form method="post" action="<?= e($admin->url('leads/telegram')) ?>">
      <?= Csrf::field() ?>

      <label class="field">
        <span class="field__label"><?= ate('Токен бота') ?></span>
        <input type="text" name="telegram_token" value=""
               placeholder="<?= $telegram['token'] ? ate('сохранён — оставьте пустым, чтобы не менять') : '123456:AA…' ?>">
        <span class="field__hint"><?= ate('Выдаёт @BotFather. Показывать его здесь мы не будем.') ?></span>
      </label>

      <label class="field">
        <span class="field__label"><?= ate('Куда слать') ?></span>
        <input type="text" name="telegram_chat" value="<?= e((string) $telegram['chat']) ?>" placeholder="123456789">
        <span class="field__hint"><?= ate('Несколько адресатов — через запятую. Обычно список набирают кнопкой «Определить чат», вручную править не нужно.') ?></span>
      </label>

      <button type="submit" class="btn btn--primary"><?= ate('Сохранить') ?></button>
    </form>

    <?php /* Кому уходят заявки: список, а не одна строка — уведомления часто нужны нескольким */ ?>
    <div class="chat-list">
      <div class="field__label"><?= ate('Заявки уходят') ?></div>

      <?php if ($telegram['chats'] === []): ?>
        <p class="muted"><?= ate('Пока никуда. Напишите боту «/start» и нажмите «Определить чат».') ?></p>
      <?php else: ?>
        <?php foreach ($telegram['chats'] as $chat): ?>
          <div class="chat-list__row">
            <code><?= e($chat) ?></code>
            <?= str_starts_with($chat, '-') ? '<span class="muted">' . ate('группа') . '</span>' : '' ?>

            <form method="post" action="<?= e($admin->url('leads/drop-chat')) ?>" class="inline-form"
                  data-confirm="<?= ate('Убрать этот чат? Заявки перестанут в него приходить.') ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="chat" value="<?= e($chat) ?>">
              <button type="submit" class="link link--danger"><?= ate('Убрать') ?></button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="btn-row" style="margin-top: 16px">
      <form method="post" action="<?= e($admin->url('leads/detect')) ?>">
        <?= Csrf::field() ?>
        <button type="submit" class="btn"><?= ate('Добавить чат') ?></button>
      </form>

      <form method="post" action="<?= e($admin->url('leads/test')) ?>">
        <?= Csrf::field() ?>
        <button type="submit" class="btn"><?= ate('Отправить проверочное сообщение') ?></button>
      </form>
    </div>
  </div>
<?php endif; ?>

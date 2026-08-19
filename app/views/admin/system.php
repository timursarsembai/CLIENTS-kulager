<?php
declare(strict_types=1);

/**
 * Страница «Состояние». На shared-хостинге нет консоли, поэтому
 * всё, что нужно для диагностики, показываем здесь.
 *
 * @var Admin $admin
 * @var string $php
 * @var array $extensions
 * @var array $limits
 * @var array $writable
 * @var array $migrations
 * @var int $contentFiles
 * @var int $cachedPages
 * @var array $security
 * @var bool $canZip
 */
?>
<h1 class="page-title"><?= ate('Состояние') ?></h1>

<div class="card">
  <h2 class="card__title"><?= ate('Резервная копия') ?></h2>
  <p class="card__lead">
    <?= ate('В копию попадают все тексты страниц, настройки и загруженные файлы.') ?>
    <?php if (!$canZip): ?>
      <?= at('Расширение ZipArchive недоступно, поэтому выгрузим только дамп базы — файлы из %s скопируйте отдельно.',
             '<code>assets/uploads</code>') ?>
    <?php else: ?>
      <?= ate('Скачается один архив: дамп базы и каталог загрузок.') ?>
    <?php endif; ?>
    <?= ate('Шаблоны и стили в копию не входят — они лежат в исходниках проекта.') ?>
  </p>

  <form method="post" action="<?= e($admin->url('backup')) ?>">
    <?= Csrf::field() ?>
    <button type="submit" class="btn btn--primary"><?= ate('Скачать копию') ?></button>
    <a href="<?= e($admin->url('log')) ?>" class="btn"><?= ate('Журнал действий') ?></a>
  </form>
</div>

<div class="card">
  <h2 class="card__title"><?= ate('Восстановление контента из файлов') ?></h2>
  <p class="card__lead">
    <?= at('Страницы жили в файлах %s до переезда в базу — сейчас их там %d. Переезд уже состоялся: сайт берёт контент из базы, а файлы остались слепком исходной вёрстки.',
           '<code>app/content</code>', $contentFiles) ?>
  </p>

  <div class="notice notice--warn">
    <?= at('Эта кнопка нужна только в одном случае — %s, если что-то безнадёжно испорчено. Она %s: и тексты блоков, и меню. В обычной работе она не нужна.',
           '<strong>' . ate('вернуть страницы к исходному виду') . '</strong>',
           '<strong>' . ate('сотрёт все правки, сделанные через админку') . '</strong>') ?>
  </div>

  <form method="post" action="<?= e($admin->url('import')) ?>"
        data-confirm="<?= ate('Все правки, сделанные через админку, будут заменены содержимым файлов. Это не отменяется. Продолжить?') ?>">
    <?= Csrf::field() ?>
    <button type="submit" class="btn"><?= ate('Вернуть контент из файлов') ?></button>
  </form>
</div>

<div class="card">
  <h2 class="card__title"><?= ate('Безопасность') ?></h2>

  <table class="table">
    <tbody>
      <tr>
        <th><?= ate('Соединение') ?></th>
        <td>
          <?php if ($security['https']): ?>
            <span class="pill pill--ok">HTTPS</span>
          <?php else: ?>
            <span class="pill pill--warn"><?= ate('без HTTPS') ?></span> <?= ate('пароли идут открытым текстом') ?>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th><?= ate('Отладка') ?></th>
        <td>
          <?php if ($security['debug']): ?>
            <span class="pill pill--warn"><?= ate('включена') ?></span> <?= ate('посетителям видны тексты ошибок') ?>
          <?php else: ?>
            <span class="pill pill--ok"><?= ate('выключена') ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th><?= ate('Вход по коду') ?></th>
        <td>
          <?php if ($security['two_factor'] > 0): ?>
            <span class="pill pill--ok"><?= ate('у %d из %d', (int) $security['two_factor'], count($security['users'])) ?></span>
          <?php else: ?>
            <span class="pill pill--warn"><?= ate('ни у кого') ?></span>
            <?= ate('включается в разделе «Профиль»') ?>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th><?= ate('Неудачные входы за сутки') ?></th>
        <td>
          <?= e((string) $security['failed']) ?>
          <?php if ($security['failed'] > 20): ?>
            <span class="pill pill--warn"><?= ate('похоже на подбор') ?></span>
          <?php endif; ?>
        </td>
      </tr>
    </tbody>
  </table>

  <p class="muted"><?= ate('После десяти неудачных попыток вход блокируется на 15 минут — и по адресу, с которого подбирают, и по самой почте. Сессия закрывается через два часа без действий и через двенадцать часов в любом случае.') ?></p>
</div>

<div class="card">
  <h2 class="card__title"><?= ate('Кэш страниц') ?></h2>
  <p class="card__lead">
    <?= at('Готовые страницы сохраняются в %s и отдаются без обращения к базе. Сейчас в кэше: %d. Любая правка в админке сбрасывает его сама — кнопка нужна, если данные меняли мимо админки.',
           '<code>app/cache</code>', $cachedPages) ?>
  </p>

  <form method="post" action="<?= e($admin->url('cache')) ?>">
    <?= Csrf::field() ?>
    <button type="submit" class="btn"><?= ate('Сбросить кэш') ?></button>
  </form>
</div>

<div class="card">
  <h2 class="card__title"><?= ate('Окружение') ?></h2>

  <table class="table">
    <tbody>
      <tr><th>PHP</th><td><?= e($php) ?></td></tr>

      <?php foreach ($extensions as $name => $loaded): ?>
        <tr>
          <th><?= e($name) ?></th>
          <td>
            <span class="pill pill--<?= $loaded ? 'ok' : 'error' ?>"><?= $loaded ? ate('есть') : ate('отсутствует') ?></span>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php foreach ($limits as $name => $value): ?>
        <tr><th><?= e($name) ?></th><td><?= e((string) $value) ?></td></tr>
      <?php endforeach; ?>

      <?php foreach ($writable as $name => $state): ?>
        <tr>
          <th><?= e($name) ?></th>
          <td>
            <?php if ($state === null): ?>
              <span class="muted"><?= ate('каталога нет') ?></span>
            <?php else: ?>
              <span class="pill pill--<?= $state ? 'ok' : 'error' ?>"><?= $state ? ate('запись доступна') : ate('нет прав на запись') ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2 class="card__title"><?= ate('Миграции') ?></h2>

  <?php if ($migrations['pending'] !== []): ?>
    <div class="notice notice--warn"><?= ate('Не применено: %s', implode(', ', $migrations['pending'])) ?></div>
    <form method="post" action="<?= e($admin->url('migrate')) ?>">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn--primary"><?= ate('Применить') ?></button>
    </form>
  <?php else: ?>
    <p class="muted"><?= ate('Все применены: %s', implode(', ', $migrations['applied'])) ?></p>
  <?php endif; ?>
</div>

<?php
declare(strict_types=1);

/**
 * @var Site   $site
 * @var Admin  $admin
 * @var Auth   $auth
 * @var string $content
 * @var string $title
 * @var array  $messages
 */

$user = $auth->check() ? $auth->user() : null;

/*
 * Админка оформлена в светлой теме «Фарфор»: рабочий инструмент читается
 * лучше на светлом, а фирменный вид даёт не цвет, а типографика и сетка.
 * Если темы «Фарфор» нет, берём тему сайта.
 */
$themes = $site->themes();
$themeVars = $themes['light']['vars'] ?? ($themes[$site->defaultTheme()]['vars'] ?? []);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title !== '' ? $title . ' — админка KULAGER' : 'Админка KULAGER') ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">

<style>
:root {
<?php foreach ($themeVars as $name => $value): ?>
  <?= $name ?>: <?= $value ?>;
<?php endforeach; ?>
}
<?php $logo = $site->contact('logo'); ?>
<?php if ($logo !== ''): ?>
<?php /* Логотип в шапке — тот же, что выбран в настройках сайта */ ?>
.topbar__brand::before {
  -webkit-mask-image: url(<?= e($site->asset($logo)) ?>);
  mask-image: url(<?= e($site->asset($logo)) ?>);
}
<?php endif; ?>
</style>
<link rel="stylesheet" href="<?= e($site->asset('css/admin.css')) ?>">
</head>
<body>

<?php if ($user): ?>
<header class="topbar">
  <a href="<?= e($admin->url()) ?>" class="topbar__brand">KULAGER<span>админка</span></a>

  <?php
  /* Пункты меню: раздел => подпись. Открытый подсвечивается */
  $menu = [
      'pages'    => 'Страницы',
      'menu'     => 'Меню',
      'leads'    => 'Заявки',
      'media'    => 'Медиатека',
      'settings' => 'Настройки',
  ];

  if ($auth->isAdmin()) {
      $menu += [
          'users'  => 'Пользователи',
          'seo'    => 'SEO',
          'themes' => 'Оформление',
          'system' => 'Состояние',
      ];
  }

  // Правка страницы и её блоков живёт по адресу page/… — это тоже «Страницы»
  $current = $admin->section() === 'page' ? 'pages' : $admin->section();
  ?>

  <nav class="topbar__nav">
    <?php foreach ($menu as $key => $label): ?>
      <a href="<?= e($admin->url($key)) ?>"<?= $current === $key ? ' class="is-active" aria-current="page"' : '' ?>>
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>

    <a href="/" target="_blank" rel="noopener">Сайт ↗</a>
  </nav>

  <div class="topbar__user">
    <?php /* Профиль и выход — кнопки: они действия, а не навигация по разделам */ ?>
    <a href="<?= e($admin->url('profile')) ?>"
       class="btn btn--small<?= $admin->section() === 'profile' ? ' btn--primary' : '' ?>">
      <?= e($user['name'] ?: $user['email']) ?>
    </a>

    <form method="post" action="<?= e($admin->url('logout')) ?>">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn--small">Выйти</button>
    </form>
  </div>
</header>
<?php endif; ?>

<main class="<?= $user ? 'shell' : 'shell shell--narrow' ?>">
  <?php foreach ($messages as $message): ?>
    <div class="notice notice--ok"><?= e($message) ?></div>
  <?php endforeach; ?>

  <?= $content ?>
</main>

<?php if ($user): ?>
<?php /* Окно выбора картинки: одно на страницу, открывается из любого поля */ ?>
<div class="picker" data-picker hidden>
  <div class="picker__backdrop" data-picker-close></div>

  <div class="picker__window" role="dialog" aria-modal="true" aria-label="Выбор изображения">
    <div class="picker__head">
      <div class="picker__tabs">
        <button type="button" class="picker__tab is-active" data-picker-tab="library">Библиотека</button>
        <button type="button" class="picker__tab" data-picker-tab="upload">Загрузить</button>
      </div>
      <button type="button" class="picker__close" data-picker-close title="Закрыть">✕</button>
    </div>

    <div class="picker__body" data-picker-panel="library">
      <p class="picker__empty" data-picker-status>Загружаем библиотеку…</p>
      <div class="picker__grid" data-picker-grid></div>
    </div>

    <div class="picker__body" data-picker-panel="upload" hidden>
      <label class="picker__drop" data-picker-drop>
        <input type="file" name="files[]" accept="image/*" multiple data-picker-file hidden>
        <span class="picker__drop-title">Перетащите файлы сюда</span>
        <span class="picker__drop-hint">или нажмите, чтобы выбрать на компьютере</span>
      </label>
      <p class="picker__empty" data-picker-upload-status hidden></p>
    </div>
  </div>
</div>

<script nonce="<?= e($admin->nonce()) ?>">window.KULAGER_TOKEN = <?= json_encode(Csrf::token()) ?>;</script>
<?php endif; ?>

<script src="<?= e($site->asset('js/admin.js')) ?>" defer></script>
</body>
</html>

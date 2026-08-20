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
<title><?= e($title !== '' ? $title . at(' — админка KULAGER') : at('Админка KULAGER')) ?></title>

<?php /* Иконка вкладки: SVG для нынешних браузеров, ICO — запасной вариант */ ?>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="apple-touch-icon" href="<?= e($site->asset('img/apple-touch-icon.png')) ?>">

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
<?php /* Логотип из настроек сайта: в шапке админки и на экране входа */ ?>
.topbar__brand::before,
.gate__logo {
  -webkit-mask-image: url(<?= e($site->asset($logo)) ?>);
  mask-image: url(<?= e($site->asset($logo)) ?>);
}
<?php endif; ?>
</style>
<link rel="stylesheet" href="<?= e($site->asset('css/admin.css')) ?>">
<link rel="stylesheet" href="<?= e($site->asset('css/picker.css')) ?>">
</head>
<body>

<?php if ($user): ?>
<header class="topbar" data-topbar>
  <a href="<?= e($admin->url()) ?>" class="topbar__brand">KULAGER<span><?= ate('админка') ?></span></a>

  <?php
  /* Пункты меню: раздел => подпись. Открытый подсвечивается */
  $menu = [
      'pages'    => at('Страницы'),
      'menu'     => at('Меню'),
      'leads'    => at('Заявки'),
      'media'    => at('Медиатека'),
      'settings' => at('Настройки'),
  ];

  if ($auth->isAdmin()) {
      $menu += [
          'users'  => at('Пользователи'),
          'seo'    => 'SEO',
          'themes' => at('Оформление'),
          'system' => at('Состояние'),
      ];
  }

  // Правка страницы и её блоков живёт по адресу page/… — это тоже «Страницы»
  $current = $admin->section() === 'page' ? 'pages' : $admin->section();
  ?>

  <?php /* На телефоне разделы уезжают в выдвижную панель за этой кнопкой */ ?>
  <button type="button" class="topbar__burger" data-topbar-toggle aria-expanded="false"
          aria-controls="admin-nav" aria-label="<?= ate('Разделы') ?>" title="<?= ate('Разделы') ?>">
    <span></span><span></span><span></span>
  </button>

  <nav class="topbar__nav" id="admin-nav">
    <?php /* Видна только на телефоне: панель перекрывает шапку целиком */ ?>
    <div class="topbar__nav-head">
      <span><?= ate('Разделы') ?></span>
      <button type="button" class="topbar__nav-close" data-topbar-close
              aria-label="<?= ate('Закрыть') ?>" title="<?= ate('Закрыть') ?>">&times;</button>
    </div>

    <?php foreach ($menu as $key => $label): ?>
      <a href="<?= e($admin->url($key)) ?>"<?= $current === $key ? ' class="is-active" aria-current="page"' : '' ?>>
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>

    <a href="/" target="_blank" rel="noopener"><?= ate('Сайт') ?> ↗</a>
  </nav>

  <div class="topbar__user">
    <?php /* Язык интерфейса: у каждого свой */ ?>
    <form method="post" action="<?= e($admin->url('lang')) ?>" class="topbar__lang">
      <?= Csrf::field() ?>
      <input type="hidden" name="back" value="<?= e($admin->section()) ?>">

      <?php foreach ($site->locales() as $code => $meta): ?>
        <button type="submit" name="locale" value="<?= e($code) ?>"
                class="topbar__lang-item<?= AdminLang::locale() === $code ? ' is-active' : '' ?>">
          <?= e((string) $meta['short']) ?>
        </button>
      <?php endforeach; ?>
    </form>

    <?php /* Профиль и выход — кнопки: они действия, а не навигация по разделам */ ?>
    <a href="<?= e($admin->url('profile')) ?>"
       class="btn btn--small<?= $admin->section() === 'profile' ? ' btn--primary' : '' ?>">
      <?= e($user['name'] ?: $user['email']) ?>
    </a>

    <?php /* Выход — значком: подпись занимала место, а действие узнаётся по иконке */ ?>
    <form method="post" action="<?= e($admin->url('logout')) ?>">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn--small btn--icon"
              title="<?= ate('Выйти') ?>" aria-label="<?= ate('Выйти') ?>">
        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
          <path d="M6.5 2.5h-4v11h4" fill="none" stroke="currentColor" stroke-width="1.6"/>
          <path d="M9 5l3 3-3 3M12 8H6" fill="none" stroke="currentColor" stroke-width="1.6"/>
        </svg>
      </button>
    </form>
  </div>
</header>

<?php /* Затемнение под выдвижной панелью: закрывает её по касанию мимо */ ?>
<div class="topbar__scrim" data-topbar-scrim></div>
<?php endif; ?>

<?php if ($user): ?>
<main class="shell">
  <?php foreach ($messages as $message): ?>
    <div class="notice notice--ok"><?= e($message) ?></div>
  <?php endforeach; ?>

  <?= $content ?>
</main>
<?php else: ?>
<?php /* Гостю показываем не урезанную админку, а отдельный экран входа */ ?>
<main class="gate">
  <div class="gate__inner">
    <a class="gate__brand" href="/" title="<?= ate('На сайт') ?>">
      <span class="gate__logo" role="img" aria-label="KULAGER"></span>
    </a>

    <?php foreach ($messages as $message): ?>
      <div class="notice notice--ok"><?= e($message) ?></div>
    <?php endforeach; ?>

    <?= $content ?>

    <p class="gate__foot"><a href="/"><?= ate('Вернуться на сайт') ?></a></p>
  </div>
</main>
<?php endif; ?>

<?php if ($user): ?>
<?php /* Окно выбора картинки: одно на страницу, открывается из любого поля */ ?>
<?= $view->partial('picker') ?>

<script nonce="<?= e($admin->nonce()) ?>">window.KULAGER_TOKEN = <?= json_encode(Csrf::token()) ?>;</script>
<?php endif; ?>

<script src="<?= e($site->asset('js/picker.js')) ?>" defer></script>
<script src="<?= e($site->asset('js/admin.js')) ?>" defer></script>
</body>
</html>

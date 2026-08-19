<?php
declare(strict_types=1);

/**
 * @var Site   $site
 * @var View   $view
 * @var string $content
 * @var array  $meta
 */

$meta = $meta ?? [];
$themes = $site->themes();
$defaultTheme = $site->defaultTheme();
$startVars = $themes[$defaultTheme]['vars'] ?? [];

$localeMeta = $site->localeMeta();

// Заголовок, описание и картинка: своё у страницы, иначе общее из настроек SEO
$title = $site->pageTitle((string) ($meta['title'] ?? ''));
$description = $site->pageDescription((string) ($meta['description'] ?? ''));
$image = $site->pageImage((string) ($meta['og_image'] ?? ''));
$ogImage = $image !== '' ? $site->assetUrl($image) : '';
$canonical = (string) ($meta['canonical'] ?? '') !== ''
    ? $site->absoluteUrl((string) $meta['canonical'])
    : $site->canonical();

// Страницу закрывают от индексации либо целиком по сайту, либо поштучно
$noindex = $site->siteNoindex() || !empty($meta['noindex']);

// JSON для переключателя тем: имя, кружок и переменные — всё из одного места
$themesJson = [];
foreach ($themes as $id => $theme) {
    $themesJson[$id] = ['name' => $theme['name'], 'vars' => $theme['vars']];
}
?>
<!DOCTYPE html>
<html lang="<?= e($localeMeta['html']) ?>" data-theme="<?= e($defaultTheme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<meta name="author" content="<?= e($site->contact('company')) ?>">
<?php if ($noindex): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php foreach ($site->verifications() as $name => $code): ?>
<meta name="<?= e($name) ?>" content="<?= e($code) ?>">
<?php endforeach; ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php foreach ($site->alternates() as $altLocale => $altUrl): ?>
<link rel="alternate" hreflang="<?= e($site->localeMeta($altLocale)['html']) ?>" href="<?= e($altUrl) ?>">
<?php endforeach; ?>

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($site->siteName()) ?>">
<meta property="og:locale" content="<?= e($localeMeta['og']) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImage !== ''): ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<meta name="theme-color" content="<?= e((string) ($meta['theme_color'] ?? $startVars['--bg'] ?? '#08131F')) ?>">

<?php /* Иконка вкладки: SVG для нынешних браузеров, ICO — запасной вариант */ ?>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="apple-touch-icon" href="<?= e($site->asset('img/apple-touch-icon.png')) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">

<style>
:root {
<?php foreach ($startVars as $name => $value): ?>
  <?= $name ?>: <?= $value ?>;
<?php endforeach; ?>
}
</style>
<link rel="stylesheet" href="<?= e($site->asset('css/site.css')) ?>">
<?php $logo = $site->contact('logo'); ?>
<?php if ($logo !== ''): ?>
<?php /* Логотип задан в админке — перекрываем маску из стилей */ ?>
<style>
.logo {
  -webkit-mask-image: url(<?= e($site->asset($logo)) ?>);
  mask-image: url(<?= e($site->asset($logo)) ?>);
}
</style>
<?php endif; ?>

<script id="kulager-themes" type="application/json"><?= json_encode($themesJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script>
/* Тема применяется до первой отрисовки, иначе страница мигает чужими цветами */
(function () {
  try {
    var themes = JSON.parse(document.getElementById('kulager-themes').textContent);
    var saved = null;
    try { saved = localStorage.getItem('kulager-theme'); } catch (e) {}
    var id = saved && themes[saved]
      ? saved
      : (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
    if (!themes[id]) return;
    var vars = themes[id].vars;
    for (var name in vars) document.documentElement.style.setProperty(name, vars[name]);
    document.documentElement.setAttribute('data-theme', id);
  } catch (e) {}
})();
</script>
<?= $view->partial('schema', ['meta' => $meta, 'canonical' => $canonical, 'ogImage' => $ogImage]) ?>
<?php /* Счётчики: не подключаем редактору, чтобы не считать свои же заходы */ ?>
<?= $site->editMode() ? '' : $site->countersHead() ?>
</head>
<body>

<div class="page">
<?= $view->partial('header') ?>

<?= $content ?>

<?= $view->partial('footer') ?>
</div>

<?= $view->partial('bar', ['meta' => $meta]) ?>
<?= $view->partial('drawer') ?>

<script src="<?= e($site->asset('js/site.js')) ?>" defer></script>
<?= $site->editMode() ? '' : $site->countersBody() ?>

<?php if ($site->adminSession()): ?>
  <?php /* Панель администратора: видна вошедшему, гостю её нет в разметке вовсе */ ?>
  <link rel="stylesheet" href="<?= e($site->asset('css/edit.css')) ?>">
  <link rel="stylesheet" href="<?= e($site->asset('css/picker.css')) ?>">

  <?= $view->partial('adminbar', [
      'adminBase' => '/' . trim((string) $site->config('admin_path', 'admin'), '/'),
      'pageId'    => $site->editPageId(),
  ]) ?>

  <?php if ($site->editMode()): ?>
    <?php /* То же окно выбора картинки, что и в админке */ ?>
    <?= $view->partial('picker') ?>

    <script>window.KULAGER_EDIT_TOKEN = <?= json_encode(Csrf::token()) ?>;</script>
    <script src="<?= e($site->asset('js/picker.js')) ?>" defer></script>
    <script src="<?= e($site->asset('js/edit.js')) ?>" defer></script>
  <?php endif; ?>
<?php endif; ?>
</body>
</html>

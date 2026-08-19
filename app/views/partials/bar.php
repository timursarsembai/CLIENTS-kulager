<?php
declare(strict_types=1);

/**
 * Плавающая панель призыва — появляется, когда посетитель ушёл ниже первого экрана.
 * Тексты и кнопка задаются страницей через $meta['bar'], иначе берётся
 * общий для сайта вариант.
 *
 * @var Site  $site
 * @var View  $view
 * @var array $meta
 */

$bar = ($meta['bar'] ?? []) + [
    'title'    => $site->t('bar.title'),
    'subtitle' => $site->t('bar.subtitle'),
    'label'    => $site->t('bar.label'),
    'message'  => $site->t('bar.message'),
];
?>
<div class="bar" data-bar>
  <div class="bar__inner">
    <?php /* Панель задаётся страницей, поэтому правка пишется в неё, а не в блок */ ?>
    <div class="bar__text">
      <div class="bar__title"<?= $view->editablePage('bar.title') ?>><?= e($bar['title']) ?></div>
      <div class="bar__subtitle"<?= $view->editablePage('bar.subtitle') ?>><?= e($bar['subtitle']) ?></div>
    </div>
    <a href="<?= e($site->whatsapp($bar['message'])) ?>" target="_blank" rel="noopener" class="btn btn--wa">
      <img src="<?= e($site->asset('img/whatsapp.svg')) ?>" alt="" width="20" height="20">
      <span<?= $view->editablePage('bar.label') ?>><?= e($bar['label']) ?></span>
    </a>
  </div>
</div>

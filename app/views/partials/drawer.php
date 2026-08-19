<?php
declare(strict_types=1);

/** @var Site $site @var View $view */

$nav = $site->nav();
$themes = $site->themes();
?>
<div class="backdrop" data-backdrop></div>

<aside class="drawer" id="site-drawer" data-drawer aria-label="<?= e($site->t('menu')) ?>" aria-hidden="true">
  <div class="drawer__head">
    <span class="logo logo--sm" role="img" aria-label="KULAGER"></span>
    <button type="button" class="drawer__close" data-drawer-close aria-label="<?= e($site->t('menu.close')) ?>">✕</button>
  </div>

  <nav class="drawer__nav">
    <?php foreach ($nav['main'] ?? [] as $item): ?>
      <a href="<?= e($site->url($item['url'])) ?>" class="drawer__link" data-drawer-close<?= $view->editableNav($item, 'title') ?>><?= e($item['title']) ?></a>
    <?php endforeach; ?>

    <div class="drawer__group"<?= $view->editableText('menu.models') ?>><?= e($site->t('menu.models')) ?></div>
    <?php foreach ($nav['models'] ?? [] as $item): ?>
      <a href="<?= e($site->url($item['url'])) ?>" class="drawer__link drawer__link--2" data-drawer-close<?= $view->editableNav($item, 'title') ?>><?= e($item['title']) ?></a>
    <?php endforeach; ?>

    <div class="drawer__group"<?= $view->editableText('menu.industries') ?>><?= e($site->t('menu.industries')) ?></div>
    <a href="<?= e($site->url($nav['industries_all']['url'] ?? 'otrasli')) ?>" class="drawer__link drawer__link--2" data-drawer-close<?= $view->editableNav($nav['industries_all'] ?? [], 'title') ?>><?= e($nav['industries_all']['title'] ?? '') ?></a>

    <?php foreach ($nav['industries'] ?? [] as $group): ?>
      <div class="drawer__group drawer__group--sub"<?= $view->editableNav($group, 'full_title') ?>><?= e($group['full_title'] ?? $group['title']) ?></div>
      <?php foreach ($group['items'] as $item): ?>
        <a href="<?= e($site->url($item['url'])) ?>" class="drawer__link drawer__link--3" data-drawer-close<?= $view->editableNav($item, 'title') ?>><?= e($item['title']) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="drawer__group"<?= $view->editableText('menu.company') ?>><?= e($site->t('menu.company')) ?></div>
    <?php foreach ($nav['company'] ?? [] as $item): ?>
      <?php if (($item['in_drawer'] ?? true) === false) { continue; } ?>
      <a href="<?= e($site->url($item['url'])) ?>" class="drawer__link drawer__link--2" data-drawer-close<?= $view->editableNav($item, 'title') ?>><?= e($item['title']) ?></a>
    <?php endforeach; ?>

    <div class="drawer__section">
      <div class="drawer__section-title"<?= $view->editableText('menu.theme') ?>><?= e($site->t('menu.theme')) ?></div>
      <div class="themes">
        <?php foreach ($themes as $id => $theme): ?>
          <button type="button"
                  class="theme-btn"
                  data-theme-pick="<?= e($id) ?>"
                  aria-pressed="false"
                  style="--dot-bg: <?= e($theme['swatch'][0]) ?>; --dot-accent: <?= e($theme['swatch'][1]) ?>">
            <span class="theme-btn__dot"></span>
            <span><?= e($theme['name']) ?></span>
            <span class="theme-btn__check">✓</span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="drawer__section drawer__section--strong">
      <div class="drawer__section-title"<?= $view->editableText('menu.language') ?>><?= e($site->t('menu.language')) ?></div>
      <?= $view->partial('lang-switch', ['modifier' => 'drawer__lang']) ?>
    </div>
  </nav>
</aside>

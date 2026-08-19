<?php
declare(strict_types=1);

/** @var Site $site @var View $view */

$waMessage = $site->t('header.wa');
?>
<header class="header">
  <div class="header__inner">
    <a href="<?= e($site->url('')) ?>" class="header__brand" aria-label="KULAGER">
      <span class="logo" role="img" aria-label="KULAGER"></span>
    </a>

    <div class="header__tools">
      <?= $view->partial('lang-switch', ['modifier' => '']) ?>

      <a href="<?= e($site->whatsapp($waMessage)) ?>" target="_blank" rel="noopener" class="btn btn--wa" style="font-size: 15px; padding: 11px 18px; min-height: 44px; gap: 8px; font-weight: 600">
        <img src="<?= e($site->asset('img/whatsapp.svg')) ?>" alt="" width="20" height="20">
        WhatsApp <span<?= $view->editableSetting('phone') ?>><?= e($site->contact('phone')) ?></span>
      </a>

      <a href="<?= e($site->whatsapp($waMessage)) ?>" target="_blank" rel="noopener" class="wa-icon" aria-label="<?= e($site->t('header.wa_label')) ?>">
        <img src="<?= e($site->asset('img/whatsapp.svg')) ?>" alt="" width="22" height="22">
      </a>

      <button type="button" class="burger" data-burger aria-label="<?= e($site->t('menu')) ?>" aria-expanded="false" aria-controls="site-drawer">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

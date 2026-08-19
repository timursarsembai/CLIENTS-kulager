<?php
declare(strict_types=1);

/** @var Site $site */
?>
<section class="wrap section" style="min-height: 46vh">
  <div class="kicker"><?= e($site->t('404.kicker')) ?></div>
  <h1 class="h2"><?= e($site->t('404.title')) ?></h1>
  <p class="lead"><?= e($site->t('404.lead')) ?></p>

  <div class="btn-row">
    <a href="<?= e($site->url('')) ?>" class="btn btn--primary"><?= e($site->t('404.home')) ?></a>
    <a href="<?= e($site->whatsapp('Здравствуйте. Интересует платформа KULAGER.')) ?>" target="_blank" rel="noopener" class="btn btn--wa">
      <img src="<?= e($site->asset('img/whatsapp.svg')) ?>" alt="" width="22" height="22">Написать в WhatsApp
    </a>
  </div>
</section>

<?php
declare(strict_types=1);

/** @var Site $site @var View $view */

$nav = $site->nav(null, 'footer');
$industries = $nav['industries'] ?? [];
?>
<footer class="footer">
  <div class="footer__top">
    <div>
      <span class="logo" role="img" aria-label="KULAGER" style="margin-bottom: 14px"></span>
      <p class="footer__note"<?= $view->editableText('footer.tagline', 'html') ?>><?= $site->t('footer.tagline') ?></p>
    </div>

    <div class="footer__contacts">
      <a href="<?= e($site->whatsapp()) ?>" target="_blank" rel="noopener">
        <img src="<?= e($site->asset('img/whatsapp-green.svg')) ?>" alt="" width="20" height="20">
        <span class="footer__contact-name">
          <span style="font-weight: 600">WhatsApp</span>
          <span style="font-size: 14px; color: var(--text-3)"<?= $view->editableSetting('phone') ?>><?= e($site->contact('phone')) ?></span>
        </span>
      </a>
      <a href="<?= e($site->phoneHref()) ?>">
        <img src="<?= e($site->asset('img/phone.svg')) ?>" alt="" width="18" height="18"><span<?= $view->editableText('footer.call') ?>><?= e($site->t('footer.call')) ?></span>
      </a>
      <a href="<?= e($site->mailHref()) ?>">
        <img src="<?= e($site->asset('img/mail.svg')) ?>" alt="" width="18" height="18"><span<?= $view->editableSetting('email') ?>><?= e($site->contact('email')) ?></span>
      </a>
    </div>

    <div>
      <div class="footer__title"<?= $view->editableText('footer.contacts') ?>><?= e($site->t('footer.contacts')) ?></div>
      <div style="display: grid; gap: 10px">
        <div>
          <div class="footer__fact"<?= $view->editableText('footer.schedule') ?>><?= e($site->t('footer.schedule')) ?></div>
          <div class="footer__value"<?= $view->editableText('contact.schedule') ?>><?= e($site->t('contact.schedule')) ?></div>
        </div>
        <div>
          <div class="footer__fact"<?= $view->editableText('footer.office') ?>><?= e($site->t('footer.office')) ?></div>
          <div class="footer__value"<?= $view->editableText('address.office') ?>><?= e($site->t('address.office')) ?></div>
        </div>
        <div>
          <div class="footer__fact"<?= $view->editableText('footer.production') ?>><?= e($site->t('footer.production')) ?></div>
          <div class="footer__value"<?= $view->editableText('address.production') ?>><?= e($site->t('address.production')) ?></div>
        </div>
      </div>
    </div>

    <div>
      <div class="footer__title"<?= $view->editableText('menu.company') ?>><?= e($site->t('menu.company')) ?></div>
      <div class="footer__links">
        <?php foreach ($nav['company'] ?? [] as $item): ?>
          <a href="<?= e($site->url($item['url'])) ?>"<?= $view->editableNav($item, $item['_field'] ?? 'title') ?>><?= e($item['title']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="footer__bottom">
    <div class="footer__bottom-inner">
      <div>
        <div class="footer__title"<?= $view->editableText('menu.models') ?>><?= e($site->t('menu.models')) ?></div>
        <div class="footer__links">
          <?php foreach ($nav['models'] ?? [] as $item): ?>
            <a href="<?= e($site->url($item['url'])) ?>"<?= $view->editableNav($item, $item['_field'] ?? 'title') ?>><?= e($item['title']) ?></a>
          <?php endforeach; ?>

          <div class="footer__title" style="margin: 14px 0 2px"<?= $view->editableText('menu.service') ?>><?= e($site->t('menu.service')) ?></div>
          <?php foreach ($nav['service'] ?? [] as $item): ?>
            <a href="<?= e($site->url($item['url'])) ?>"<?= $view->editableNav($item, $item['_field'] ?? 'title') ?>><?= e($item['title']) ?></a>
          <?php endforeach; ?>

          <hr>
          <a href="<?= e($site->url($nav['industries_all']['url'] ?? 'otrasli')) ?>"<?= $view->editableNav($nav['industries_all'] ?? [], $nav['industries_all']['_field'] ?? 'title') ?>><?= e($nav['industries_all']['title'] ?? '') ?></a>
        </div>
      </div>

      <?php foreach ($industries as $group): ?>
        <div>
          <div class="footer__title"<?= $view->editableNav($group, 'full_title') ?>><?= e($group['full_title'] ?? $group['title']) ?></div>
          <div class="footer__links">
            <?php foreach ($group['items'] as $item): ?>
              <a href="<?= e($site->url($item['url'])) ?>"<?= $view->editableNav($item, $item['_field'] ?? 'title') ?>><?= e($item['title']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php /* Служебная строка: кто владеет сайтом и как обрабатываются данные */ ?>
  <div class="footer__legal">
    <span><?= e($site->contact('company')) ?></span>
    <a href="<?= e($site->url('rekvizity')) ?>"><?= e($site->t('footer.requisites')) ?></a>
    <a href="<?= e($site->url('personalnye-dannye')) ?>"><?= e($site->t('footer.privacy')) ?></a>
  </div>
</footer>

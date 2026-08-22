<?php
declare(strict_types=1);

/**
 * Обложка с формой заявки: слева заголовок, справа короткая форма.
 *
 * Отличается от обычной обложки тем, что посетителю не нужно искать, куда
 * писать: форма стоит на первом экране. Отправляется туда же, куда и форма
 * на странице контактов, — обычным POST на /zayavka, без JavaScript тоже
 * работает.
 *
 * Подпись формы вместо CSRF-токена: страницы кэшируются и отдаются без
 * сессии, поэтому токен из сессии здесь неприменим.
 *
 * @var Site  $site
 * @var View  $view
 * @var array $block
 */

$sign = $site->formSignature();
$sent = ($_GET['zayavka'] ?? '') === 'ok';
$error = (string) ($_GET['oshibka'] ?? '');
$success = (string) ($block['success'] ?? 'Заявка отправлена. Мы свяжемся с вами в рабочее время.');
?>
<section class="hero hero--form">
  <img data-drift src="<?= e($site->asset($block['image'])) ?>"<?= $view->editableImage($block, 'image') ?> alt="<?= e($block['alt'] ?? '') ?>" class="hero__photo">
  <div class="hero__scrim"></div>

  <div class="hero__body">
    <div class="hero__inner hero-form">
      <div class="hero-form__text">
        <div class="hero__eyebrow" data-hero-in style="--d: 0s">
          <span class="hero__rule"></span>
          <span class="hero__label"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker'] ?? '') ?></span>
        </div>

        <h1 class="h1" data-hero-in style="--d: .09s"<?= $view->editable($block, 'title', 'html') ?>><?= $block['title'] ?? '' ?></h1>

        <?php if (!empty($block['lead'])): ?>
          <p class="hero__lead" data-hero-in style="--d: .18s"<?= $view->editable($block, 'lead') ?>><?= e($block['lead']) ?></p>
        <?php endif; ?>

        <?php foreach ($block['actions'] ?? [] as $index => $action): ?>
          <?= $view->partial('action', ['action' => $action + ['hero_delay' => '.27s'], 'edit' => $view->editable($block, "actions.$index.label")]) ?>
        <?php endforeach; ?>
      </div>

      <div class="hero-form__card" id="<?= e($block['id'] ?? 'zayavka') ?>" data-hero-in style="--d: .27s">
        <div class="hero-form__head">
          <div class="hero-form__title"<?= $view->editable($block, 'form_title') ?>>
            <?= e($block['form_title'] ?? 'Оставьте заявку') ?>
          </div>

          <?php if (!empty($block['form_lead'])): ?>
            <p class="hero-form__note"<?= $view->editable($block, 'form_lead') ?>><?= e($block['form_lead']) ?></p>
          <?php endif; ?>
        </div>

        <?php if ($sent): ?>
          <p class="form-note form-note--ok" data-form-success<?= $view->editable($block, 'success') ?>><?= e($success) ?></p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
          <p class="form-note form-note--error"><?= e($error) ?></p>
        <?php endif; ?>

        <form class="lead-form lead-form--narrow" method="post" action="<?= e($site->url('zayavka')) ?>"
              data-lead-form data-success="<?= e($success) ?>">
          <?php /* Время открытия проставит скрипт: страница кэшируется, серверное время тут бесполезно */ ?>
          <input type="hidden" name="started" value="0" data-form-started>
          <input type="hidden" name="sign" value="<?= e($sign) ?>">
          <input type="hidden" name="page" value="<?= e($site->path()) ?>">
          <input type="hidden" name="locale" value="<?= e($site->locale()) ?>">

          <?php /* Ловушка для роботов: людям это поле не видно */ ?>
          <div class="lead-form__trap" aria-hidden="true">
            <label>Компания<input type="text" name="company" tabindex="-1" autocomplete="off"></label>
          </div>

          <label class="lead-form__field lead-form__field--wide">
            <span<?= $view->editable($block, 'name_label') ?>><?= e($block['name_label'] ?? 'Как к вам обращаться') ?></span>
            <input type="text" name="name" required autocomplete="name">
          </label>

          <label class="lead-form__field lead-form__field--wide">
            <span<?= $view->editable($block, 'phone_label') ?>><?= e($block['phone_label'] ?? 'Телефон') ?></span>
            <input type="tel" name="phone" autocomplete="tel" placeholder="+7 (___) ___-__-__">
          </label>

          <?php if (empty($block['hide_message'])): ?>
            <label class="lead-form__field lead-form__field--wide">
              <span<?= $view->editable($block, 'message_label') ?>><?= e($block['message_label'] ?? 'Что нужно перевозить') ?></span>
              <textarea name="message" rows="2"></textarea>
            </label>
          <?php endif; ?>

          <?php
          /*
           * Согласие отмечается галочкой, а не подразумевается текстом под
           * кнопкой: закон требует согласия на сбор данных, и подтвердить
           * его должно осознанное действие человека. Текст уходит вместе
           * с заявкой — потом видно, с чем именно согласились.
           */
          $consent = (string) ($block['consent'] ?? 'Согласен на обработку персональных данных');
          ?>
          <label class="lead-form__consent lead-form__field--wide">
            <input type="checkbox" name="consent" value="1" required>
            <input type="hidden" name="consent_text" value="<?= e($consent . ' — ' . $site->url('personalnye-dannye')) ?>">
            <span<?= $view->editable($block, 'consent') ?>><?= e($consent) ?></span>
            <a href="<?= e($site->url('personalnye-dannye')) ?>" target="_blank" rel="noopener"><?= e($block['consent_link'] ?? 'Политика') ?></a>
          </label>

          <div class="lead-form__foot">
            <button type="submit" class="btn btn--primary"<?= $view->editable($block, 'submit') ?>>
              <?= e($block['submit'] ?? 'Отправить заявку') ?>
            </button>
          </div>

          <?php if (!empty($block['note'])): ?>
            <p class="form-note hero-form__legal"<?= $view->editable($block, 'note') ?>><?= e($block['note']) ?></p>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</section>

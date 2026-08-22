<?php
declare(strict_types=1);

/**
 * Форма заявки. Отправляется обычным POST на /zayavka — без JavaScript тоже
 * работает; скрипт лишь избавляет от перезагрузки страницы.
 *
 * Вместо CSRF-токена — подпись формы: страницы кэшируются и отдаются без
 * сессии, поэтому токен из сессии здесь неприменим.
 *
 * @var Site  $site
 * @var View  $view
 * @var array $block
 */

$sign = $site->formSignature();
$sent = ($_GET['zayavka'] ?? '') === 'ok';
$error = (string) ($_GET['oshibka'] ?? '');

$body = static function () use ($site, $view, $block, $sign, $sent, $error): void { ?>
  <?php if (!empty($block['kicker'])): ?>
    <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker']) ?></div>
  <?php endif; ?>

  <h2 class="h2" style="max-width: 24ch"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h2>

  <?php if (!empty($block['lead'])): ?>
    <p class="lead" style="max-width: 56ch"<?= $view->editable($block, 'lead') ?>><?= e($block['lead']) ?></p>
  <?php endif; ?>

  <?php if ($sent): ?>
    <p class="form-note form-note--ok" data-form-success<?= $view->editable($block, 'success') ?>>
      <?= e($block['success'] ?? 'Заявка отправлена. Мы свяжемся с вами.') ?>
    </p>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <p class="form-note form-note--error"><?= e($error) ?></p>
  <?php endif; ?>

  <form class="lead-form" method="post" action="<?= e($site->url('zayavka')) ?>" data-lead-form>
    <?php /* Время открытия проставит скрипт: страница кэшируется, серверное время тут бесполезно */ ?>
    <input type="hidden" name="started" value="0" data-form-started>
    <input type="hidden" name="sign" value="<?= e($sign) ?>">
    <input type="hidden" name="page" value="<?= e($site->path()) ?>">
    <input type="hidden" name="locale" value="<?= e($site->locale()) ?>">

    <?php /* Ловушка для роботов: людям это поле не видно */ ?>
    <div class="lead-form__trap" aria-hidden="true">
      <label>Компания<input type="text" name="company" tabindex="-1" autocomplete="off"></label>
    </div>

    <label class="lead-form__field">
      <span<?= $view->editable($block, 'name_label') ?>><?= e($block['name_label'] ?? 'Как к вам обращаться') ?></span>
      <input type="text" name="name" required autocomplete="name">
    </label>

    <label class="lead-form__field">
      <span<?= $view->editable($block, 'phone_label') ?>><?= e($block['phone_label'] ?? 'Телефон') ?></span>
      <input type="tel" name="phone" autocomplete="tel" placeholder="+7 (___) ___-__-__">
    </label>

    <label class="lead-form__field">
      <span<?= $view->editable($block, 'email_label') ?>><?= e($block['email_label'] ?? 'Почта') ?></span>
      <input type="email" name="email" autocomplete="email">
    </label>

    <label class="lead-form__field lead-form__field--wide">
      <span<?= $view->editable($block, 'message_label') ?>><?= e($block['message_label'] ?? 'Что нужно перевозить') ?></span>
      <textarea name="message" rows="3"></textarea>
    </label>

        <?php
        /*
         * Согласие отмечается галочкой, а не подразумевается текстом под
         * кнопкой: закон требует согласия на сбор данных, и подтвердить его
         * должно осознанное действие человека.
         *
         * Ссылкой становится часть фразы, а не вся строка: щелчок по остальному
         * тексту переключает саму галочку, как и положено подписи поля.
         */
        $consentUrl = $site->url('personalnye-dannye');
        $consentText = (string) ($block['consent'] ?? 'Согласен с политикой обработки персональных данных');
        $consentLink = (string) ($block['consent_link'] ?? 'политикой обработки персональных данных');
        $at = $consentLink !== '' ? mb_strpos($consentText, $consentLink) : false;

        $consentHtml = $at === false
            ? e($consentText)
            : e(mb_substr($consentText, 0, $at))
                . '<a href="' . e($consentUrl) . '" target="_blank" rel="noopener">' . e($consentLink) . '</a>'
                . e(mb_substr($consentText, $at + mb_strlen($consentLink)));
        ?>
        <label class="lead-form__consent lead-form__field--wide">
          <input type="checkbox" name="consent" value="1" required>
          <input type="hidden" name="consent_text" value="<?= e($consentText . ' — ' . $consentUrl) ?>">
          <span><?= $consentHtml ?></span>
        </label>

    <div class="lead-form__foot">
      <button type="submit" class="btn btn--primary"<?= $view->editable($block, 'submit') ?>>
        <?= e($block['submit'] ?? 'Отправить заявку') ?>
      </button>

      <?php foreach ($block['actions'] ?? [] as $index => $action): ?>
        <?= $view->partial('action', [
            'action' => $action,
            'edit'   => $view->editable($block, "actions.$index.label"),
        ]) ?>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($block['note'])): ?>
      <p class="form-note"<?= $view->editable($block, 'note') ?>><?= e($block['note']) ?></p>
    <?php endif; ?>
  </form>
<?php };
?>
<?php if (!empty($block['panel'])): ?>
  <section id="<?= e($block['id'] ?? 'zayavka') ?>" class="band">
    <div class="wrap section"><?php $body(); ?></div>
  </section>
<?php else: ?>
  <section id="<?= e($block['id'] ?? 'zayavka') ?>" class="wrap section"><?php $body(); ?></section>
<?php endif; ?>

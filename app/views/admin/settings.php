<?php
declare(strict_types=1);

/**
 * @var Admin $admin
 * @var array $fields
 * @var array $values
 * @var array $defaults
 */
?>
<h1 class="page-title"><?= ate('Настройки') ?></h1>

<div class="card card--form">
  <h2 class="card__title"><?= ate('Контакты и адреса') ?></h2>
  <p class="card__lead"><?= ate('Эти данные подставляются во все страницы сразу: телефоны, кнопки WhatsApp, подвал, микроразметку для поисковиков. Пустое поле означает «взять значение из настроек проекта».') ?></p>

  <form method="post" action="<?= e($admin->url('settings')) ?>">
    <?= Csrf::field() ?>

    <?php foreach ($fields as $key => $field): ?>
      <?php
      $value = (string) ($values[$key] ?? '');
      $default = (string) ($defaults[$key] ?? '');
      $isDefault = $value === $default;
      ?>
      <label class="field">
        <span class="field__label"><?= e((string) $field['label']) ?></span>

        <?php if (($field['type'] ?? '') === 'image'): ?>
          <?php /* Логотип выбирается из медиатеки — тем же окном, что и картинки блоков */ ?>
          <?= FormBuilder::imageField($key, $value) ?>
        <?php else: ?>
          <input type="text" name="<?= e($key) ?>" value="<?= e($isDefault ? '' : $value) ?>"
                 placeholder="<?= e($default) ?>">
        <?php endif; ?>

        <?php if (isset($field['hint'])): ?>
          <span class="field__hint"><?= e((string) $field['hint']) ?></span>
        <?php endif; ?>
      </label>
    <?php endforeach; ?>

    <button type="submit" class="btn btn--primary"><?= ate('Сохранить') ?></button>
  </form>
</div>

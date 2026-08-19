<?php
declare(strict_types=1);

/**
 * Форма редактирования блока. Поля строятся по описанию из реестра,
 * поэтому этот шаблон не знает ничего о конкретных типах блоков.
 *
 * @var Admin  $admin
 * @var array  $page
 * @var string $locale
 * @var array  $block
 * @var array  $definition
 * @var array  $data
 * @var array  $errors
 */

$base = $admin->url('page/' . $page['id'] . '/' . $locale);
?>
<div class="crumbs">
  <a href="<?= e($admin->url('pages')) ?>">Страницы</a> →
  <a href="<?= e($base) ?>"><?= e($page['page_key']) ?></a> →
  <?= e((string) $definition['title']) ?>
</div>

<h1 class="page-title"><?= e((string) $definition['title']) ?></h1>

<?php if (isset($definition['hint'])): ?>
  <p class="lead-text"><?= e((string) $definition['hint']) ?></p>
<?php endif; ?>

<?php if ($errors !== []): ?>
  <div class="notice notice--error">
    Не сохранено — проверьте поля:
    <ul>
      <?php foreach ($errors as $message): ?>
        <li><?= e($message) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= e($base . '/block/' . $block['id']) ?>" class="card block-form">
  <?= Csrf::field() ?>

  <?= FormBuilder::fields($definition['fields'], $data) ?>

  <div class="block-form__actions">
    <button type="submit" class="btn btn--primary"><?= ate('Сохранить') ?></button>
    <a href="<?= e($base) ?>" class="btn">Отмена</a>

    <span class="block-form__spacer"></span>

    <button type="submit" form="block-delete" class="btn btn--danger"><?= ate('Удалить блок') ?></button>
  </div>
</form>

<form method="post" action="<?= e($base . '/block/' . $block['id'] . '/delete') ?>"
      id="block-delete" data-confirm="Удалить блок безвозвратно?">
  <?= Csrf::field() ?>
</form>

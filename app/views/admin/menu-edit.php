<?php
declare(strict_types=1);

/**
 * Правка одного меню: пункты редактируются списком и сохраняются разом.
 *
 * Порядок меняется перетаскиванием — тем же способом, что и блоки страницы.
 * В меню отраслей пункты живут внутри групп, поэтому группы выводятся
 * заголовками, а их пункты — под ними.
 *
 * @var Admin  $admin
 * @var string $locale
 * @var array  $locales
 * @var string $menu
 * @var string $title
 * @var list<array> $items
 * @var bool   $grouped
 */

$base = $admin->url('menu/' . $locale . '/' . $menu);

/* Группы и их пункты — только для меню отраслей */
$groups = [];
$plain = [];

foreach ($items as $item) {
    if ($grouped && $item['parent_id'] === null) {
        $groups[(int) $item['id']] = ['row' => $item, 'items' => []];
        continue;
    }

    if ($grouped) {
        $parent = (int) $item['parent_id'];

        if (isset($groups[$parent])) {
            $groups[$parent]['items'][] = $item;
        }

        continue;
    }

    $plain[] = $item;
}

/** Поля одного пункта. */
$fields = static function (array $item, bool $isGroup) use ($admin, $base): void {
    $name = 'items[' . (int) $item['id'] . ']';
    ?>
    <div class="menu-item" data-list-item data-menu-id="<?= e((string) $item['id']) ?>">
      <div class="list-item__handle" data-list-handle title="<?= ate('Перетащите, чтобы поменять порядок') ?>">⋮⋮</div>

      <div class="menu-item__fields">
        <label class="field">
          <span class="field__label"><?= ate('Название') ?></span>
          <input type="text" name="<?= e($name) ?>[title]" value="<?= e((string) $item['title']) ?>" required>
        </label>

        <?php if ($isGroup): ?>
          <label class="field">
            <span class="field__label"><?= ate('Полное название') ?></span>
            <input type="text" name="<?= e($name) ?>[full_title]" value="<?= e((string) $item['full_title']) ?>">
            <span class="field__hint"><?= ate('В шапке название сокращают, в боковом меню показывают целиком.') ?></span>
          </label>
        <?php else: ?>
          <label class="field">
            <span class="field__label"><?= ate('Ссылка') ?></span>
            <?= FormBuilder::pageField($name . '[url]', (string) $item['url']) ?>
          </label>

          <label class="field">
            <span class="field__label"><?= ate('Название в подвале') ?></span>
            <input type="text" name="<?= e($name) ?>[footer_title]" value="<?= e((string) ($item['footer_title'] ?? '')) ?>"
                   placeholder="<?= e((string) $item['title']) ?>">
            <span class="field__hint"><?= ate('Пусто — в подвале то же название. Заполните, если там нужно другое.') ?></span>
          </label>

          <label class="field field--check">
            <input type="hidden" name="<?= e($name) ?>[in_drawer]" value="0">
            <input type="checkbox" name="<?= e($name) ?>[in_drawer]" value="1"<?= $item['in_drawer'] ? ' checked' : '' ?>>
            <span class="field__label"><?= ate('Показывать в боковом меню') ?></span>
          </label>

          <label class="field field--check">
            <input type="hidden" name="<?= e($name) ?>[in_footer]" value="0">
            <input type="checkbox" name="<?= e($name) ?>[in_footer]" value="1"<?= ($item['in_footer'] ?? 1) ? ' checked' : '' ?>>
            <span class="field__label"><?= ate('Показывать в подвале') ?></span>
          </label>
        <?php endif; ?>
      </div>

      <button type="submit" form="menu-delete-<?= e((string) $item['id']) ?>"
              class="list-item__remove" title="<?= ate('Удалить') ?>">✕</button>
    </div>
    <?php
};
?>
<div class="crumbs">
  <a href="<?= e($admin->url('menu/' . $locale)) ?>"><?= ate('Меню') ?></a> → <?= ate($title) ?>
</div>

<h1 class="page-title"><?= ate($title) ?></h1>

<div class="btn-row">
  <?php foreach ($locales as $code => $lang): ?>
    <a href="<?= e($admin->url('menu/' . $code . '/' . $menu)) ?>"
       class="btn<?= $code === $locale ? ' btn--primary' : '' ?>"><?= e((string) $lang['name']) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($items === []): ?>
  <div class="card">
    <p class="muted"><?= ate('В этом меню пока нет пунктов.') ?></p>
  </div>
<?php endif; ?>

<form method="post" action="<?= e($base . '/save') ?>" class="card">
  <?= Csrf::field() ?>

  <div class="menu-list" data-menu-sortable data-menu-reorder="<?= e($base . '/reorder') ?>">
    <?php if ($grouped): ?>
      <?php foreach ($groups as $group): ?>
        <div class="menu-group">
          <?php $fields($group['row'], true); ?>

          <div class="menu-group__items">
            <?php foreach ($group['items'] as $item): ?>
              <?php $fields($item, false); ?>
            <?php endforeach; ?>

            <details class="menu-add">
              <summary><?= ate('Добавить отрасль в группу') ?></summary>
              <div class="menu-add__body">
                <input type="text" form="menu-add-<?= e((string) $group['row']['id']) ?>"
                       name="title" placeholder="<?= ate('Название') ?>" required>
                <input type="text" form="menu-add-<?= e((string) $group['row']['id']) ?>"
                       name="url" placeholder="otrasli/sklady">
                <button type="submit" form="menu-add-<?= e((string) $group['row']['id']) ?>"
                        class="btn btn--small"><?= ate('Добавить') ?></button>
              </div>
            </details>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <?php foreach ($plain as $item): ?>
        <?php $fields($item, false); ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="block-form__actions">
    <button type="submit" class="btn btn--primary"><?= ate('Сохранить') ?></button>
    <a href="<?= e($admin->url('menu/' . $locale)) ?>" class="btn"><?= ate('К списку меню') ?></a>
  </div>
</form>

<div class="card">
  <h2 class="card__title"><?= $grouped ? ate('Добавить группу') : ate('Добавить пункт') ?></h2>

  <form method="post" action="<?= e($base . '/add') ?>" class="menu-add__body">
    <?= Csrf::field() ?>
    <input type="text" name="title" placeholder="<?= ate('Название') ?>" required>
    <?php if (!$grouped): ?>
      <input type="text" name="url" placeholder="o-kompanii">
    <?php endif; ?>
    <button type="submit" class="btn"><?= ate('Добавить') ?></button>
  </form>
</div>

<?php /* Формы удаления и добавления в группу — вне основной формы, иначе вложенность */ ?>
<?php foreach ($items as $item): ?>
  <form method="post" action="<?= e($base . '/delete') ?>" id="menu-delete-<?= e((string) $item['id']) ?>"
        data-confirm="<?= ate('Удалить пункт меню?') ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
  </form>
<?php endforeach; ?>

<?php if ($grouped): ?>
  <?php foreach ($groups as $group): ?>
    <form method="post" action="<?= e($base . '/add') ?>" id="menu-add-<?= e((string) $group['row']['id']) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="parent_id" value="<?= e((string) $group['row']['id']) ?>">
    </form>
  <?php endforeach; ?>
<?php endif; ?>

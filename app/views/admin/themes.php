<?php
declare(strict_types=1);

/**
 * Список цветовых тем сайта.
 *
 * @var Admin  $admin
 * @var list<array> $themes
 * @var string $current
 */
?>
<h1 class="page-title"><?= ate('Оформление') ?></h1>

<p class="lead-text"><?= ate('Посетитель может переключить тему сам — здесь задаётся, какая открывается по умолчанию, и правятся цвета каждой темы.') ?></p>

<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th></th>
        <th><?= ate('Тема') ?></th>
        <th><?= ate('Тип') ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($themes as $theme): ?>
        <?php $isCurrent = $theme['theme_key'] === $current; ?>
        <tr>
          <td>
            <span class="theme-dot" style="background: <?= e((string) $theme['swatch_bg']) ?>">
              <i style="background: <?= e((string) $theme['swatch_accent']) ?>"></i>
            </span>
          </td>
          <td>
            <?= ate((string) $theme['name']) ?>
            <?php if ($isCurrent): ?>
              <span class="pill pill--ok"><?= ate('по умолчанию') ?></span>
            <?php endif; ?>
          </td>
          <td class="muted"><?= $theme['is_builtin'] ? ate('встроенная') : ate('своя') ?></td>
          <td class="table__actions">
            <a href="<?= e($admin->url('themes/' . $theme['id'])) ?>" class="btn btn--small"><?= ate('Править') ?></a>

            <?php if (!$isCurrent): ?>
              <form method="post" action="<?= e($admin->url('themes/default')) ?>" class="inline-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="theme_key" value="<?= e((string) $theme['theme_key']) ?>">
                <button type="submit" class="link"><?= ate('Сделать основной') ?></button>
              </form>
            <?php endif; ?>

            <?php if (!$theme['is_builtin']): ?>
              <form method="post" action="<?= e($admin->url('themes/' . $theme['id'] . '/delete')) ?>"
                    class="inline-form" data-confirm="<?= ate('Удалить тему?') ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="link link--danger"><?= ate('Удалить') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2 class="card__title"><?= ate('Новая тема') ?></h2>
  <p class="card__lead"><?= ate('Новая тема создаётся копией существующей — останется поправить цвета, а не заполнять три десятка значений с нуля.') ?></p>

  <form method="post" action="<?= e($admin->url('themes/add')) ?>" class="menu-add__body">
    <?= Csrf::field() ?>

    <input type="text" name="name" placeholder="<?= ate('Название темы') ?>" required>

    <select name="source">
      <?php foreach ($themes as $theme): ?>
        <option value="<?= e((string) $theme['theme_key']) ?>"><?= ate('на основе') ?> «<?= ate((string) $theme['name']) ?>»</option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn--primary"><?= ate('Создать') ?></button>
  </form>
</div>

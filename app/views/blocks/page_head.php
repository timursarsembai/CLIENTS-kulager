<?php
declare(strict_types=1);

/**
 * Заголовок страницы: надзаголовок, H1 и вводный текст.
 * С него начинаются страницы без большой обложки — «Отрасли», «О нас»,
 * «Контакты», «Реквизиты».
 *
 * @var View  $view
 * @var array $block
 */
?>
<section class="wrap section section--flush-bottom" style="padding-bottom: clamp(24px, 3vw, 40px)">
  <?php if (!empty($block['kicker'])): ?>
    <div class="kicker"<?= $view->editable($block, 'kicker') ?>><?= e($block['kicker']) ?></div>
  <?php endif; ?>

  <h1 class="h2" style="max-width: 22ch"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h1>

  <?php if (!empty($block['lead'])): ?>
    <p class="lead" style="max-width: 60ch; margin-bottom: 0"<?= $view->editable($block, 'lead') ?>><?= e($block['lead']) ?></p>
  <?php endif; ?>
</section>

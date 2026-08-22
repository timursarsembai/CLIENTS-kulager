<?php
declare(strict_types=1);

/**
 * Правовой документ: политика, оферта, согласие.
 *
 * Выглядит листом бумаги, а не частью сайта, — так его и читают, и печатают.
 * Разделы нумеруются сами: в документе на два десятка пунктов ручная нумерация
 * разъезжается при первой же правке.
 *
 * @var Site  $site
 * @var View  $view
 * @var array $block
 */
?>
<section id="<?= e($block['id'] ?? 'document') ?>" class="legal">
  <article class="legal__sheet">
    <header class="legal__head">
      <h1 class="legal__title"<?= $view->editable($block, 'title') ?>><?= e($block['title'] ?? '') ?></h1>

      <?php if (!empty($block['updated'])): ?>
        <p class="legal__date"<?= $view->editable($block, 'updated') ?>><?= e($block['updated']) ?></p>
      <?php endif; ?>
    </header>

    <?php if (!empty($block['intro'])): ?>
      <div class="legal__intro"<?= $view->editable($block, 'intro', 'html') ?>><?= $block['intro'] ?></div>
    <?php endif; ?>

    <?php foreach ($block['sections'] ?? [] as $index => $section): ?>
      <section class="legal__section">
        <h2 class="legal__section-title">
          <span class="legal__num"><?= e((string) ($index + 1)) ?>.</span>
          <span<?= $view->editable($block, "sections.$index.title") ?>><?= e($section['title'] ?? '') ?></span>
        </h2>

        <?php if (!empty($section['text'])): ?>
          <div class="legal__text"<?= $view->editable($block, "sections.$index.text", 'html') ?>><?= $section['text'] ?></div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <?php if (!empty($block['footer'])): ?>
      <footer class="legal__foot"<?= $view->editable($block, 'footer', 'html') ?>><?= $block['footer'] ?></footer>
    <?php endif; ?>
  </article>
</section>

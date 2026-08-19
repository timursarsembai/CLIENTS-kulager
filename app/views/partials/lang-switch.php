<?php
declare(strict_types=1);

/**
 * Переключатель языка. Ведёт на ту же страницу в другом языке —
 * адреса берутся из карты маршрутов текущей страницы.
 *
 * @var Site   $site
 * @var string $modifier дополнительный класс контейнера
 */

$alternates = $site->alternates();
$current = $site->locale();
?>
<div class="lang <?= e($modifier ?? '') ?>">
<?php foreach ($site->locales() as $code => $meta): ?>
  <?php if ($code === $current): ?>
    <span class="lang__item lang__item--active"><?= e($meta['short']) ?></span>
  <?php else: ?>
    <a href="<?= e($alternates[$code] ?? $site->url('', $code)) ?>" class="lang__item" hreflang="<?= e($meta['html']) ?>"><?= e($meta['short']) ?></a>
  <?php endif; ?>
<?php endforeach; ?>
</div>

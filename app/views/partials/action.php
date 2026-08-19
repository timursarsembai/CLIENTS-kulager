<?php
declare(strict_types=1);

/**
 * Кнопка-призыв. Стили: whatsapp | primary | ghost | ghost-strong.
 * Для whatsapp адрес собирается из текста обращения, чтобы номер жил
 * в одном месте — в конфигурации.
 *
 * @var Site   $site
 * @var array  $action
 * @var string $edit атрибуты правки подписи, если страница открыта в режиме правки
 */

$edit = $edit ?? '';

$style = (string) ($action['style'] ?? 'primary');
$isWhatsApp = $style === 'whatsapp';

if ($isWhatsApp) {
    $href = $site->whatsapp((string) ($action['message'] ?? ''));
} else {
    $url = (string) ($action['url'] ?? '');
    // Внешние ссылки и mailto/tel оставляем как есть, внутренние — через маршрутизатор
    $href = preg_match('~^([a-z]+:|/|#)~i', $url) ? $url : $site->url($url);
}

$external = $isWhatsApp || str_starts_with($href, 'http') || ($action['target'] ?? '') === '_blank';

$class = classes([
    'btn',
    'btn--wa'           => $isWhatsApp,
    'btn--primary'      => $style === 'primary',
    'btn--ghost'        => $style === 'ghost',
    'btn--ghost-strong' => $style === 'ghost-strong',
    'btn--lg'           => ($action['size'] ?? '') === 'lg',
    'btn--sm'           => ($action['size'] ?? '') === 'sm',
]);
?>
<a href="<?= e($href) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?> class="<?= e($class) ?>"<?= isset($action['hero_delay']) ? ' data-hero-in style="--d: ' . e((string) $action['hero_delay']) . '"' : '' ?>>
<?php if ($isWhatsApp): ?><img src="<?= e($site->asset('img/whatsapp.svg')) ?>" alt="" width="22" height="22"><?php endif; ?>
<span<?= $edit ?>><?= e((string) ($action['label'] ?? '')) ?></span></a>

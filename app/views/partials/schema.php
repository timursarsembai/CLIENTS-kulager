<?php
declare(strict_types=1);

/**
 * Микроразметка schema.org. Организация и страница описываются здесь для всех
 * страниц сразу, а специфичные узлы страница добавляет через $meta['schema'].
 *
 * @var Site   $site
 * @var array  $meta
 * @var string $canonical
 * @var string $ogImage
 */

$graph = [
    [
        '@type'         => 'Organization',
        '@id'           => $site->baseUrl() . '/#org',
        'name'          => $site->contact('company'),
        'alternateName' => 'KULAGER',
        'url'           => $site->absoluteUrl(''),
        'logo'          => $site->assetUrl('img/deck/logo-blue.png'),
        'telephone'     => $site->contact('phone_schema'),
        'email'         => $site->contact('email'),
        'address'       => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'ул. Наурызбай батыра 8, БЦ Коба, 3 этаж',
            'addressLocality' => 'Алматы',
            'addressCountry'  => 'KZ',
        ],
        'areaServed' => ['@type' => 'Country', 'name' => 'Kazakhstan'],
        'sameAs'     => [$site->whatsapp()],
    ],
    [
        '@type'       => 'WebPage',
        '@id'         => $canonical . '#page',
        'url'         => $canonical,
        'name'        => (string) ($meta['title'] ?? ''),
        'description' => (string) ($meta['description'] ?? ''),
        'inLanguage'  => $site->localeMeta()['html'],
        'isPartOf'    => ['@id' => $site->baseUrl() . '/#org'],
    ],
];

if ($ogImage !== '') {
    $graph[1]['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $ogImage];
}

foreach ((array) ($meta['schema'] ?? []) as $node) {
    $graph[] = $node;
}

$payload = ['@context' => 'https://schema.org', '@graph' => $graph];
?>
<?php /* JSON_HEX_TAG: в названиях и адресах может оказаться «</script>» */ ?>
<script type="application/ld+json"><?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>

<?php
declare(strict_types=1);

/**
 * Карта страниц сайта.
 *
 * key         — идентификатор маршрута
 * slug        — адрес для каждого языка, '' означает корень.
 *               Плейсхолдер {slug} делает маршрут шаблонным: подходит любой
 *               адрес, для которого найден файл контента.
 * template    — шаблон в views/pages/
 * content     — имя файла контента в app/content/ (по умолчанию — key)
 * content_dir — каталог контента для шаблонных маршрутов
 *
 * Когда появится CMS, этот файл станет резервным источником для страниц,
 * которых ещё нет в базе.
 */
return [
    'home' => [
        'slug'     => ['ru' => '', 'kk' => ''],
        'template' => 'home',
    ],

    'otrasli' => [
        'slug'     => ['ru' => 'otrasli', 'kk' => 'otrasli'],
        'template' => 'page',
    ],

    'dileram' => [
        'slug'     => ['ru' => 'dileram', 'kk' => 'dileram'],
        'template' => 'page',
    ],

    'kontakty' => [
        'slug'     => ['ru' => 'kontakty', 'kk' => 'kontakty'],
        'template' => 'page',
    ],

    'rekvizity' => [
        'slug'     => ['ru' => 'rekvizity', 'kk' => 'rekvizity'],
        'template' => 'page',
    ],

    'o-kompanii' => [
        'slug'     => ['ru' => 'o-kompanii', 'kk' => 'o-kompanii'],
        'template' => 'page',
    ],

    // 22 отрасли применения — одна вёрстка, разный контент
    'industry' => [
        'slug'        => ['ru' => 'otrasli/{slug}', 'kk' => 'otrasli/{slug}'],
        'template'    => 'industry',
        'content_dir' => 'industries',
    ],

    // Модели техники — MC1, MC1e
    'model' => [
        'slug'        => ['ru' => 'modeli/{slug}', 'kk' => 'modeli/{slug}'],
        'template'    => 'model',
        'content_dir' => 'models',
    ],
];

<?php
declare(strict_types=1);

/**
 * Навигация: общая для шапки, бокового меню и подвала.
 * Адреса — человекопонятные, вместо служебных имён из макета.
 */
return [
    /*
     * Магазин запчастей и сервисный центр остаются демонстрационными
     * разделами — в меню их нет, пока нет самих страниц.
     */
    'main' => [
        ['title' => 'Главная', 'url' => ''],
        ['title' => 'О нас',   'url' => 'o-kompanii'],
    ],

    'models' => [
        ['title' => 'KULAGER MC1 — бензин', 'url' => 'modeli/mc1'],
        ['title' => 'KULAGER MC1e — электро', 'url' => 'modeli/mc1e'],
    ],

    // Раздел «Обслуживание» появится вместе со страницами гарантии и ТО
    'service' => [],

    'company' => [
        ['title' => 'О нас',            'url' => 'o-kompanii', 'in_drawer' => false],
        ['title' => 'Обратная связь',   'url' => 'kontakty'],
        ['title' => 'Реквизиты',        'url' => 'rekvizity'],
        ['title' => 'Дилерам и партнёрам', 'url' => 'dileram'],
    ],

    // 22 отрасли применения, сгруппированные так же, как в макете
    'industries' => [
        [
            'title' => 'АПК',
            'full_title' => 'Агропромышленный комплекс',
            'items' => [
                ['title' => 'Фермерские хозяйства',      'url' => 'otrasli/fermerskie-hozyaystva'],

                ['title' => 'Животноводческие фермы', 'url' => 'otrasli/zhivotnovodcheskie-fermy'],
                ['title' => 'Птицефермы',                'url' => 'otrasli/pticefermy'],
                ['title' => 'Тепличные хозяйства',       'url' => 'otrasli/teplicy'],
                ['title' => 'Садоводческие хозяйства',   'url' => 'otrasli/sadovodcheskie-hozyaystva'],
                ['title' => 'Питомники растений',        'url' => 'otrasli/pitomniki-rasteniy'],
                ['title' => 'Элеваторы',                 'url' => 'otrasli/elevatory'],
                ['title' => 'Склады сельхозпредприятий', 'url' => 'otrasli/sklady-selhozpredpriyatiy'],
            ],
        ],
        [
            'title' => 'Коммунальное хозяйство',
            'full_title' => 'Коммунальное хозяйство',
            'items' => [
                ['title' => 'Акиматы',                   'url' => 'otrasli/akimaty'],
                ['title' => 'КСК и ОСИ',                 'url' => 'otrasli/ksk-osi'],
                ['title' => 'Базы благоустройства',      'url' => 'otrasli/bazy-blagoustroystva'],
                ['title' => 'Коммунальные предприятия',  'url' => 'otrasli/kommunalnye-predpriyatiya'],
                ['title' => 'Садово-парковые хозяйства', 'url' => 'otrasli/sadovo-parkovye-hozyaystva'],
                ['title' => 'Кладбища',                  'url' => 'otrasli/kladbischa'],
                ['title' => 'Базы озеленения',           'url' => 'otrasli/bazy-ozeleneniya'],
            ],
        ],
        [
            'title' => 'Бизнес',
            'full_title' => 'Бизнес',
            'items' => [
                ['title' => 'Строительные компании',      'url' => 'otrasli/stroitelnye-kompanii'],
                ['title' => 'Склады',                     'url' => 'otrasli/sklady'],
                ['title' => 'Базы стройматериалов',       'url' => 'otrasli/bazy-stroymaterialov'],
                ['title' => 'Карьеры',                    'url' => 'otrasli/karyery'],
                ['title' => 'Производственные площадки',  'url' => 'otrasli/proizvodstvennye-ploschadki'],
                ['title' => 'Рынки',                      'url' => 'otrasli/rynki'],
                ['title' => 'Большие магазины и склады',  'url' => 'otrasli/magaziny-sklady'],
            ],
        ],
    ],

    'industries_all' => ['title' => 'Все отрасли применения', 'url' => 'otrasli'],
];

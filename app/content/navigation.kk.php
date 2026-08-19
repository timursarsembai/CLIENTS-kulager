<?php
declare(strict_types=1);

/**
 * Навигация на казахском. Адреса те же, что в русской версии, —
 * языковой префикс подставляет Site::url().
 *
 * Названия взяты из казахских макетов Claude Design. Пункты, которых
 * в казахских макетах нет (гарантия, регламент ТО, оферта, политика,
 * страница «О нас»), сюда не включены: этих страниц пока нет и на русском.
 */
return [
    // Магазина и сервисного центра пока нет — см. комментарий в navigation.ru.php
    'main' => [
        ['title' => 'Басты бет', 'url' => ''],
    ],

    'models' => [
        ['title' => 'KULAGER MC1 — бензин',  'url' => 'modeli/mc1'],
        ['title' => 'KULAGER MC1e — электро', 'url' => 'modeli/mc1e'],
    ],

    'service' => [],

    'company' => [
        ['title' => 'Кері байланыс',                'url' => 'kontakty'],
        ['title' => 'Деректемелер',                 'url' => 'rekvizity'],
        ['title' => 'Дилерлер мен серіктестерге',   'url' => 'dileram'],
    ],

    'industries' => [
        [
            'title' => 'АӨК',
            'full_title' => 'Агроөнеркәсіптік кешен',
            'items' => [
                ['title' => 'Фермер қожалықтары',       'url' => 'otrasli/fermerskie-hozyaystva'],
                ['title' => 'Мал фермалары',            'url' => 'otrasli/zhivotnovodcheskie-fermy'],
                ['title' => 'Құс фабрикалары',          'url' => 'otrasli/pticefermy'],
                ['title' => 'Жылыжай шаруашылықтары',   'url' => 'otrasli/teplicy'],
                ['title' => 'Бағбандық шаруашылықтар',  'url' => 'otrasli/sadovodcheskie-hozyaystva'],
                ['title' => 'Өсімдік питомниктері',     'url' => 'otrasli/pitomniki-rasteniy'],
                ['title' => 'Элеваторлар',              'url' => 'otrasli/elevatory'],
                ['title' => 'Ауылшаруашылық қоралары',  'url' => 'otrasli/sklady-selhozpredpriyatiy'],
            ],
        ],
        [
            'title' => 'Коммуналдық шаруашылық',
            'full_title' => 'Коммуналдық шаруашылық',
            'items' => [
                ['title' => 'Әкімдіктер',                  'url' => 'otrasli/akimaty'],
                ['title' => 'КСК және ОСИ',                'url' => 'otrasli/ksk-osi'],
                ['title' => 'Абаттандыру базалары',        'url' => 'otrasli/bazy-blagoustroystva'],
                ['title' => 'Коммуналдық кәсіпорындар',    'url' => 'otrasli/kommunalnye-predpriyatiya'],
                ['title' => 'Саябақ-бақ шаруашылықтары',   'url' => 'otrasli/sadovo-parkovye-hozyaystva'],
                ['title' => 'Зираттар',                    'url' => 'otrasli/kladbischa'],
                ['title' => 'Көгалдандыру базалары',       'url' => 'otrasli/bazy-ozeleneniya'],
            ],
        ],
        [
            'title' => 'Бизнес',
            'full_title' => 'Бизнес',
            'items' => [
                ['title' => 'Құрылыс компаниялары',            'url' => 'otrasli/stroitelnye-kompanii'],
                ['title' => 'Қоралар',                         'url' => 'otrasli/sklady'],
                ['title' => 'Құрылыс материалдары базалары',   'url' => 'otrasli/bazy-stroymaterialov'],
                ['title' => 'Кеніштер',                        'url' => 'otrasli/karyery'],
                ['title' => 'Өндірістік алаңдар',              'url' => 'otrasli/proizvodstvennye-ploschadki'],
                ['title' => 'Базарлар',                        'url' => 'otrasli/rynki'],
                ['title' => 'Үлкен дүкендер мен қоралар',      'url' => 'otrasli/magaziny-sklady'],
            ],
        ],
    ],

    'industries_all' => ['title' => 'Барлық қолданылу салалары', 'url' => 'otrasli'],
];

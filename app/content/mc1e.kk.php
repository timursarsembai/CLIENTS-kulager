<?php
declare(strict_types=1);

/**
 * Отрасль: KULAGER MC1e — 800 кг дейінгі электрлі жүк трициклі
 *
 * Файл собран из макета Claude Design. Общие для всех отраслей блоки
 * берутся из content/shared.ru.php — правятся один раз для всех страниц.
 *
 * @var Site $site
 */

$shared = require APP_DIR . '/content/shared.kk.php';

$wa = 'Сәлеметсіз бе. KULAGER MC1e электрлі нұсқасы қызықтырады. Шарттарды, жинақтауды және мерзімдерді жіберуіңізді сұраймыз.';

return [
    'meta' => [
        'title'       => 'KULAGER MC1e — 800 кг дейінгі электрлі жүк трициклі | Тапсырыспен',
        'description' => 'KULAGER MC1e: жүк платформасының электрлі нұсқасы. Номиналды жүктеме 800 кг дейін, шанағы 1,1 × 1,6 м, электр қозғалтқышы 1,2 кВт, жүйесі 60 В. Шығарынды мен шу жоқ — жылыжай кешендеріне, қораларға және жабық аумақтарға. Өндіріс Қазақстан.',
        'og_image'    => 'img/deck/og-ru.png',
        'og_type'     => 'product',
        'bar'         => [
            'title'    => 'KULAGER MC1e — электро',
            'subtitle' => '800 кг дейін · 60 В · тапсырыспен',
            'label'    => 'MC1e сұрату',
            'message'  => 'Сәлеметсіз бе. KULAGER MC1e қызықтырады.',
        ],
    ],

    'blocks' => [

        [
            'type'       => 'product_hero',
            'alt'        => 'KULAGER MC1',
            'kicker'     => 'Платформаның электрлі нұсқасы',
            'gallery'    => $shared['gallery'],
            'marks'      => [
                [
                    'label' => 'Қазақстанда шығарылған',
                    'style' => 'accent',
                ],
                [
                    'label' => 'Тапсырыспен',
                    'style' => 'outline',
                ],
            ],
            'title'      => 'KULAGER MC1e',
            'lead'       => 'Сол шасси электр тартқышта. Тыныштық пен шығарындының болмауы маңызды жабық аумақтарға, жылыжай кешендеріне және ішкі технологиялық өтпелерге арналған.',
            'offer'      => [
                'price'   => 'Сұрау бойынша',
                'note'    => 'жинақтау және мерзімдер',
                'points'  => [
                    'Тиеу және жеткізу күн ішінде, 9:00–19:00',
                    'Алматы облысы: 2 бірліктен жеткізу тегін',
                    'Қазақстан бойынша — тек көтерме, жеткізу келісім бойынша',
                    'Партия: шарттар мен баға тапсырыс көлеміне қарай',
                ],
                'actions' => [
                    [
                        'label'   => 'MC1e сұрату',
                        'style'   => 'whatsapp',
                        'message' => $wa,
                    ],
                    [
                        'label' => 'Сипаттамалары',
                        'style' => 'ghost',
                        'url'   => '#specs',
                    ],
                ],
            ],
            'quickspecs' => [
                [
                    'value'   => '800 кг',
                    'caption' => 'номиналды жүктеме',
                ],
                [
                    'value'   => '1,1 × 1,6 м',
                    'caption' => 'шанақ өлшемдері',
                ],
                [
                    'value'   => '1,2 кВт',
                    'caption' => 'электржүйе 60 В',
                ],
                [
                    'value'   => 'Нөл',
                    'caption' => 'маршруттағы шығарынды',
                ],
            ],
        ],

        [
            'type'   => 'testdrive',
            'title'  => '',
            'text'   => '',
            'action' => [
                'label'   => '',
                'style'   => 'whatsapp',
                'message' => '',
            ],
        ],

        [
            'type'    => 'cards',
            'id'      => 'tasks',
            'panel'   => true,
            'surface' => 'surface-2',
            'kicker'  => 'Қолданылу орны',
            'title'   => 'Электрлі нұсқа не береді',
            'lead'    => 'Платформаның сол міндеттері, бірақ шығарынды мен қозғалтқыш шуы жоқ.',
            'min'     => 240,
            'items'   => [
                [
                    'title' => 'Жабық аумақтар',
                    'text'  => 'Жылыжай кешендері, қоралар, цехтар, өндірістік корпустар — үй-жай ішіндегі жұмыс.',
                ],
                [
                    'title' => 'Тыныш жүріс',
                    'text'  => 'Қызметкерлерге кедергі жасамайды және тұйық кеңістікте шу жүктемесі тудырмайды.',
                ],
                [
                    'title' => 'Маршрутта нөл шығарынды',
                    'text'  => 'Микроклимат пен санитарлық талаптар маңызды жерде пайдаланылған газ жоқ.',
                ],
                [
                    'title' => 'Қарапайым қызмет көрсету',
                    'text'  => 'Бензин нұсқасына қарағанда тораптар аз. Конструкция мен сервис — платформадағыдай.',
                ],
            ],
        ],

        [
            'type'   => 'cards',
            'id'     => 'season',
            'kicker' => '',
            'title'  => '',
            'lead'   => '',
            'min'    => 240,
            'items'  => [],
        ],

        [
            'type'    => 'cards',
            'id'      => 'fleet',
            'panel'   => true,
            'surface' => 'surface-2',
            'kicker'  => '',
            'title'   => '',
            'lead'    => '',
            'min'     => 260,
            'items'   => [],
        ],

        [
            'type'     => 'spec_compare',
            'id'       => 'why',
            'kicker'   => '',
            'title'    => '',
            'lead'     => '',
            'columns'  => [],
            'rows'     => [],
            'footnote' => '',
            'image'    => '',
            'alt'      => 'KULAGER MC1',
        ],

        [
            'type'   => 'steps',
            'id'     => 'buy',
            'panel'  => true,
            'kicker' => '',
            'title'  => '',
            'lead'   => '',
            'items'  => [],
            'action' => [
                'label'   => '',
                'style'   => 'whatsapp',
                'message' => '',
            ],
        ],

        [
            'type'    => 'cards',
            'id'      => 'certs',
            'surface' => 'surface-2',
            'kicker'  => '',
            'title'   => '',
            'lead'    => '',
            'min'     => 260,
            'items'   => [],
        ],

        [
            'type'   => 'spec_table',
            'id'     => 'requisites',
            'kicker' => '',
            'title'  => '',
            'rows'   => [],
            'note'   => '',
        ],

        $shared['platform'],
        $shared['models'],
        $shared['solutions'],

        // У госсектора отраслевое обращение в WhatsApp, остальное общее
        array_replace_recursive($shared['gov'], [
            'callout' => ['action' => ['message' => 'Сәлеметсіз бе. Біз мемлекеттік ұйымбыз. Техникалық тапсырма бойынша KULAGER платформасы қызықтырады. КҰ және сынақтан өткізу шарттарын жіберуіңізді сұраймыз.']],
        ]),

        $shared['delivery'],
        $shared['docs'],
        $shared['parts'],
        $shared['industries'],

        [
            'type'    => 'cta_box',
            'id'      => 'lead',
            'title'   => 'Маршруттарыңызға жинақтауды талқылаймыз',
            'text'    => 'WhatsApp-қа жазыңыз — міндетті нақтылап, үстеме құрылым таңдап, мерзімдерді растаймыз.',
            'actions' => [
                ['label' => 'WhatsApp-қа жазу', 'style' => 'whatsapp', 'message' => $wa],
                ['label' => $site->contact('phone'), 'style' => 'ghost-strong', 'url' => $site->phoneHref()],
            ],
        ],
    ],
];

<?php
declare(strict_types=1);

/**
 * Отрасль: KULAGER MC1 — өздігінен түсетін шанақты 1000 кг жүк трициклі
 *
 * Файл собран из макета Claude Design. Общие для всех отраслей блоки
 * берутся из content/shared.ru.php — правятся один раз для всех страниц.
 *
 * @var Site $site
 */

$shared = require APP_DIR . '/content/shared.kk.php';

$wa = 'Сәлеметсіз бе. KULAGER MC1 қызықтырады. Қолда бар ма және жеткізу қалай?';

return [
    'meta' => [
        'title'       => 'KULAGER MC1 — өздігінен түсетін шанақты 1000 кг жүк трициклі',
        'description' => 'KULAGER MC1: номиналды жүк көтергіштігі 1000 кг, өздігінен түсетін шанағы 1300×1800 мм, ені 1,3 м, Zongshen 200 бензин қозғалтқышы. Бағасы сұрау бойынша, қолда бар, тиеу күн ішінде. Қазақстандық өндіріс, сервис пен қосалқы бөлшектер Алматыда.',
        'og_image'    => 'img/deck/og-ru.png',
        'og_type'     => 'product',
        'bar'         => [
            'title'    => 'KULAGER MC1',
            'subtitle' => 'Қолда бар · тиеу күн ішінде · 1000 кг дейін',
            'label'    => 'WhatsApp-қа жазу',
            'message'  => 'Сәлеметсіз бе. KULAGER MC1 қызықтырады.',
        ],
    ],

    'blocks' => [

        [
            'type'       => 'product_hero',
            'alt'        => 'KULAGER MC1',
            'kicker'     => 'Бензинді жүк трициклі',
            'gallery'    => $shared['gallery'],
            'marks'      => [
                [
                    'label' => 'Қазақстанда шығарылған',
                    'style' => 'accent',
                ],
                [
                    'label' => 'Қолда бар, түсі хаки',
                    'style' => 'outline',
                ],
            ],
            'title'      => 'KULAGER MC1',
            'lead'       => 'Шаруашылық, қора немесе кәсіпорын аумағы бойынша 1000 кг дейін тасиды. Өздігінен түсетін шанақ, ені 1,3 метр, бензин қозғалтқышы және өз күшіңізбен қызмет көрсету.',
            'offer'      => [
                'price'   => 'Бағасы сұрау бойынша',
                'note'    => 'көлем мен жинақтауға байланысты',
                'points'  => [
                    'Тиеу және жеткізу күн ішінде, 9:00–19:00',
                    'Алматы облысы: 2 бірліктен жеткізу тегін',
                    'Қазақстан бойынша — тек көтерме, жеткізу келісім бойынша',
                    'Партия: шарттар мен баға тапсырыс көлеміне қарай',
                ],
                'actions' => [
                    [
                        'label'   => 'WhatsApp арқылы сатып алу',
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
                    'value'   => '1000 кг',
                    'caption' => 'жүк көтергіштігі',
                ],
                [
                    'value'   => '1,3 м',
                    'caption' => 'ені',
                ],
                [
                    'value'   => 'Өздігінен',
                    'caption' => 'шанақты гидравликалық көтеру',
                ],
                [
                    'value'   => 'АИ-92',
                    'caption' => 'бензин, багы 18 л',
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
            'kicker'  => 'Міндеттер',
            'title'   => 'MC1 және MC1e не істейді',
            'lead'    => 'Толық жүк көлігі артық болатын, тоннаға дейінгі жергілікті тасымал. Платформаның екі нұсқасы — бензинді MC1 және электрлі MC1e.',
            'min'     => 240,
            'items'   => [
                [
                    'title' => 'Бір рейсте бір тонна',
                    'text'  => 'Номиналды жүк көтергіштігі 1000 кг, жарақтандырылған массасы 500 кг. Шанағы 1300×1800 мм, борттары 360 мм.',
                ],
                [
                    'title' => 'Қолмен түсірусіз',
                    'text'  => 'Шанақты гидроцилиндр көтереді: құм, топырақ, дән, қалдық қолмен түсірілмейді.',
                ],
                [
                    'title' => 'Жүк көлігі өтпейтін жерден өтеді',
                    'text'  => 'Габариттері 3390×1300×1575 мм. Жылыжай өтпелері, тар аулалар, корпустар арасы.',
                ],
                [
                    'title' => 'Өз күшіңізбен қызмет көрсету',
                    'text'  => 'АИ-92 бензині, қарапайым конструкция, базалық сервис 15 минут, қосалқы бөлшектер Алматыда.',
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
            'title'   => 'Күн ішінде тиеуге дайынбыз',
            'text'    => 'WhatsApp-қа жазыңыз — қолда бар-жоғын растаймыз, жеткізуді есептеп, құжаттарды жібереміз.',
            'actions' => [
                ['label' => 'WhatsApp-қа жазу', 'style' => 'whatsapp', 'message' => $wa],
                ['label' => $site->contact('phone'), 'style' => 'ghost-strong', 'url' => $site->phoneHref()],
            ],
        ],
    ],
];

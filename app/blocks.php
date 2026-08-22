<?php
declare(strict_types=1);

/**
 * Реестр блоков: из этого описания строятся форма редактирования,
 * проверка значений и библиотека блоков в админке.
 *
 * Типы полей:
 *   string   — однострочный текст
 *   text     — многострочный текст
 *   richtext — текст с ограниченным набором тегов (b, i, br, a, ul/ol/li)
 *   bool     — переключатель
 *   number   — целое число
 *   select   — выбор из списка (options: значение => подпись)
 *   page     — ссылка на страницу сайта: выпадающий список вместо адреса
 *   section  — раздел страниц; ограничивает список в полях page этого блока
 *   image    — путь к картинке в assets/img
 *   action   — кнопка (подпись, стиль, адрес или текст для WhatsApp)
 *   group    — вложенная группа полей (fields)
 *   list     — повторяющаяся группа (of: набор полей или 'action'/'string')
 *
 * `hint` показывается редактору под полем — туда пишем то, что неочевидно.
 */

/** Поля кнопки — используются во многих блоках */
$action = [
    'label'   => ['type' => 'string', 'label' => 'Подпись', 'required' => true],
    'style'   => ['type' => 'select', 'label' => 'Вид', 'default' => 'primary', 'options' => [
        'whatsapp'     => 'WhatsApp (зелёная)',
        'primary'      => 'Основная (акцентная)',
        'ghost'        => 'Контурная',
        'ghost-strong' => 'Контурная, контрастная',
    ]],
    'message' => ['type' => 'text', 'label' => 'Текст сообщения в WhatsApp', 'rows' => 2,
                  'hint' => 'Только для кнопки WhatsApp: подставится в переписку.'],
    'url'     => ['type' => 'url', 'label' => 'Адрес',
                  'hint' => 'Для обычных кнопок. Внутренний адрес без слэша: modeli/mc1'],
    'size'    => ['type' => 'select', 'label' => 'Размер', 'default' => '', 'options' => [
        ''   => 'Обычный',
        'sm' => 'Компактный',
        'lg' => 'Крупный',
    ]],
];

/** Заголовочная часть, повторяющаяся почти в каждом блоке */
$heading = [
    'kicker' => ['type' => 'string', 'label' => 'Надзаголовок', 'hint' => 'Мелкая строка над заголовком.'],
    'title'  => ['type' => 'string', 'label' => 'Заголовок', 'required' => true],
    'lead'   => ['type' => 'text', 'label' => 'Вводный текст', 'rows' => 3],
];

$anchor = [
    'id' => ['type' => 'string', 'label' => 'Якорь', 'hint' => 'Латиницей. Позволяет ссылаться на секцию: #parts'],
];

$badge = [
    'label' => ['type' => 'string', 'label' => 'Текст'],
    'style' => ['type' => 'select', 'label' => 'Вид', 'default' => 'stock', 'options' => [
        'stock'          => 'В наличии (зелёная)',
        'order'          => 'Под заказ (контур)',
        'accent'         => 'Акцентная заливка',
        'outline-accent' => 'Акцентный контур',
        'outline-muted'  => 'Серый контур',
    ]],
];

return [

    /* ============================================================ обложки */

    'hero' => [
        'title'  => 'Обложка',
        'group'  => 'Верх страницы',
        'hint'   => 'Крупная фотография на весь экран с заголовком и кнопкой. Обычно первый блок главной.',
        'fields' => [
            'image'   => ['type' => 'image', 'label' => 'Фотография', 'required' => true],
            'alt'     => ['type' => 'string', 'label' => 'Описание фотографии', 'hint' => 'Для поиска и незрячих.'],
            'kicker'  => ['type' => 'string', 'label' => 'Надзаголовок'],
            'title'   => ['type' => 'richtext', 'label' => 'Заголовок', 'required' => true,
                          'hint' => 'Перенос строки делит фразу на строки.'],
            'lead'    => ['type' => 'text', 'label' => 'Вводный текст', 'rows' => 3],
            'actions' => ['type' => 'list', 'label' => 'Кнопки', 'of' => $action, 'max' => 2],
        ],
    ],

    /*
     * Та же обложка, но с формой заявки справа. Отдельным типом, а не
     * переключателем внутри обычной обложки: поля у них разные, и мешать
     * их в одной форме — значит показывать редактору лишнее.
     */
    'hero_form' => [
        'title'  => 'Обложка с формой',
        'group'  => 'Верх страницы',
        'hint'   => 'Фотография на весь экран: слева заголовок, справа короткая форма заявки.',
        'fields' => $anchor + [
            'image'   => ['type' => 'image', 'label' => 'Фотография', 'required' => true],
            'alt'     => ['type' => 'string', 'label' => 'Описание фотографии', 'hint' => 'Для поиска и незрячих.'],
            'kicker'  => ['type' => 'string', 'label' => 'Надзаголовок'],
            'title'   => ['type' => 'richtext', 'label' => 'Заголовок', 'required' => true,
                          'hint' => 'Перенос строки делит фразу на строки.'],
            'lead'    => ['type' => 'text', 'label' => 'Вводный текст', 'rows' => 3],
            'actions' => ['type' => 'list', 'label' => 'Кнопки', 'of' => $action, 'max' => 2],

            'form_title'    => ['type' => 'string', 'label' => 'Заголовок формы', 'default' => 'Оставьте заявку'],
            'form_lead'     => ['type' => 'text', 'label' => 'Строка под заголовком формы', 'rows' => 2],
            'name_label'    => ['type' => 'string', 'label' => 'Подпись поля «Имя»', 'default' => 'Как к вам обращаться'],
            'phone_label'   => ['type' => 'string', 'label' => 'Подпись поля «Телефон»', 'default' => 'Телефон'],
            'message_label' => ['type' => 'string', 'label' => 'Подпись поля «Сообщение»', 'default' => 'Что нужно перевозить'],
            'hide_message'  => ['type' => 'bool', 'label' => 'Убрать поле сообщения',
                                'hint' => 'Короче форма — больше заявок, но меньше о них известно заранее.'],
            'submit'        => ['type' => 'string', 'label' => 'Подпись кнопки', 'default' => 'Отправить заявку'],
            'consent'       => ['type' => 'string', 'label' => 'Текст согласия у галочки',
                                'default' => 'Согласен с политикой обработки персональных данных',
                                'hint' => 'Галочка обязательна: без неё заявка не отправится.'],
            'consent_link'  => ['type' => 'string', 'label' => 'Часть фразы, ставшая ссылкой',
                                'default' => 'политикой обработки персональных данных',
                                'hint' => 'Должна встречаться в тексте выше — иначе ссылки не будет.'],
            'note'          => ['type' => 'text', 'label' => 'Текст под кнопкой', 'rows' => 2],
            'success'       => ['type' => 'text', 'label' => 'Ответ после отправки', 'rows' => 2,
                                'default' => 'Заявка отправлена. Мы свяжемся с вами в рабочее время.'],
        ],
    ],

    'product_hero' => [
        'title'  => 'Карточка товара',
        'group'  => 'Верх страницы',
        'hint'   => 'Галерея снимков, заголовок, цена и короткие характеристики. Верх страниц отраслей и моделей.',
        'fields' => [
            'kicker'  => ['type' => 'string', 'label' => 'Надзаголовок', 'required' => true],
            'title'   => ['type' => 'string', 'label' => 'Заголовок', 'required' => true],
            'lead'    => ['type' => 'text', 'label' => 'Вводный текст', 'rows' => 3],
            'alt'     => ['type' => 'string', 'label' => 'Описание снимков'],
            'gallery' => ['type' => 'list', 'label' => 'Снимки', 'of' => 'image',
                          'hint' => 'Первый снимок показывается крупно.'],
            'marks'   => ['type' => 'list', 'label' => 'Метки', 'max' => 3, 'of' => [
                'label' => ['type' => 'string', 'label' => 'Текст'],
                'style' => ['type' => 'select', 'label' => 'Вид', 'default' => 'outline', 'options' => [
                    'accent'  => 'Заливка',
                    'outline' => 'Контур',
                ]],
            ]],
            'offer' => ['type' => 'group', 'label' => 'Блок с ценой', 'fields' => [
                'price'   => ['type' => 'string', 'label' => 'Цена'],
                'note'    => ['type' => 'string', 'label' => 'Уточнение рядом с ценой'],
                'points'  => ['type' => 'list', 'label' => 'Условия', 'of' => 'string'],
                'actions' => ['type' => 'list', 'label' => 'Кнопки', 'of' => $action, 'max' => 2],
            ]],
            'quickspecs' => ['type' => 'list', 'label' => 'Короткие характеристики', 'max' => 6, 'of' => [
                'value'   => ['type' => 'string', 'label' => 'Значение'],
                'caption' => ['type' => 'string', 'label' => 'Подпись'],
            ]],
        ],
    ],

    'page_head' => [
        'title'  => 'Заголовок страницы',
        'group'  => 'Верх страницы',
        'hint'   => 'Надзаголовок, крупный заголовок и вводный текст. Для страниц без большой обложки.',
        'fields' => $anchor + [
            'kicker' => ['type' => 'string', 'label' => 'Надзаголовок'],
            'title'  => ['type' => 'string', 'label' => 'Заголовок', 'required' => true],
            'lead'   => ['type' => 'text', 'label' => 'Вводный текст', 'rows' => 3],
        ],
    ],

    'photo_band' => [
        'title'  => 'Полоса с фотографией',
        'group'  => 'Верх страницы',
        'hint'   => 'Фотография на всю ширину с заголовком и показателями поверх неё.',
        'fields' => $heading + [
            'image' => ['type' => 'image', 'label' => 'Фотография', 'required' => true],
            'alt'   => ['type' => 'string', 'label' => 'Описание фотографии'],
            'stats' => ['type' => 'list', 'label' => 'Показатели', 'max' => 4, 'of' => [
                'value'   => ['type' => 'string', 'label' => 'Значение'],
                'caption' => ['type' => 'string', 'label' => 'Подпись'],
            ]],
        ],
    ],

    /* ============================================================ контент */

    'cards' => [
        'title'  => 'Карточки',
        'group'  => 'Контент',
        'hint'   => 'Заголовок и сетка карточек. Самый универсальный блок: подходит для услуг, документов, условий доставки.',
        'fields' => $anchor + $heading + [
            'source' => ['type' => 'section', 'label' => 'Раздел для ссылок', 'default' => '',
                         'hint' => 'Ограничивает список страниц в ссылках карточек. Например «Отрасли» — тогда в списке будут только страницы отраслей.'],
            'items' => ['type' => 'list', 'label' => 'Карточки', 'required' => true, 'of' => [
                'title' => ['type' => 'string', 'label' => 'Заголовок'],
                'text'  => ['type' => 'text', 'label' => 'Описание', 'rows' => 3],
                'url'   => ['type' => 'page', 'label' => 'Ссылка',
                            'hint' => 'Если заполнено, карточка станет ссылкой целиком.'],
                'label' => ['type' => 'string', 'label' => 'Метка вместо заголовка',
                            'hint' => 'Если заполнено, вместо названия выводится рубрика — как у сезонов.'],
            ]],
            'actions' => ['type' => 'list', 'label' => 'Кнопки под карточками', 'of' => $action, 'max' => 3],
            'panel'   => ['type' => 'bool', 'label' => 'На подложке', 'hint' => 'Секция с фоном во всю ширину экрана.'],
            'surface' => ['type' => 'select', 'label' => 'Фон карточек', 'default' => '', 'options' => [
                ''          => 'Обычный',
                'surface-2' => 'Приглушённый',
            ]],
            'min'    => ['type' => 'number', 'label' => 'Мин. ширина карточки, px', 'default' => 260,
                         'hint' => 'Чем больше значение, тем меньше карточек в ряду.'],
            'tall'   => ['type' => 'bool', 'label' => 'Высокие карточки'],
            'narrow' => ['type' => 'bool', 'label' => 'Ограничить ширину сетки'],
            'maxw'   => ['type' => 'number', 'label' => 'Макс. ширина сетки, px'],
        ],
    ],

    'steps' => [
        'title'  => 'Пронумерованные шаги',
        'group'  => 'Контент',
        'hint'   => 'Порядок действий: заявка, счёт, поставка. Номера проставляются сами.',
        'fields' => $anchor + $heading + [
            'items' => ['type' => 'list', 'label' => 'Шаги', 'required' => true, 'of' => [
                'title' => ['type' => 'string', 'label' => 'Заголовок'],
                'text'  => ['type' => 'text', 'label' => 'Описание', 'rows' => 3],
            ]],
            'action' => ['type' => 'group', 'label' => 'Кнопка под списком', 'fields' => $action],
            'panel'  => ['type' => 'bool', 'label' => 'На подложке'],
        ],
    ],

    'platform' => [
        'title'  => 'Платформа и надстройки',
        'group'  => 'Контент',
        'hint'   => 'Список сменных надстроек. Слева либо карточка шасси, либо фотография.',
        'fields' => $anchor + $heading + [
            'options' => ['type' => 'list', 'label' => 'Надстройки', 'required' => true, 'of' => [
                'title'     => ['type' => 'string', 'label' => 'Название'],
                'text'      => ['type' => 'text', 'label' => 'Описание', 'rows' => 2],
                'highlight' => ['type' => 'bool', 'label' => 'Выделить'],
            ]],
            'image'   => ['type' => 'image', 'label' => 'Фотография справа'],
            'alt'     => ['type' => 'string', 'label' => 'Описание фотографии'],
            'caption' => ['type' => 'string', 'label' => 'Подпись под фотографией'],
            'note'    => ['type' => 'text', 'label' => 'Примечание внизу', 'rows' => 2],
            'base'    => ['type' => 'group', 'label' => 'Карточка шасси', 'fields' => [
                'label' => ['type' => 'string', 'label' => 'Метка'],
                'image' => ['type' => 'image', 'label' => 'Фотография'],
                'alt'   => ['type' => 'string', 'label' => 'Описание фотографии'],
                'title' => ['type' => 'string', 'label' => 'Название'],
                'text'  => ['type' => 'text', 'label' => 'Описание', 'rows' => 3],
                'specs' => ['type' => 'list', 'label' => 'Характеристики', 'of' => 'string'],
            ], 'hint' => 'Если заполнено — слева карточка шасси вместо фотографии.'],
        ],
    ],

    'solutions' => [
        'title'  => 'Решения на платформе',
        'group'  => 'Контент',
        'hint'   => 'Крупная карточка, две обычные и полоса специальных версий.',
        'fields' => $anchor + $heading + [
            'featured' => ['type' => 'group', 'label' => 'Крупная карточка', 'fields' => [
                'image'  => ['type' => 'image', 'label' => 'Фотография'],
                'alt'    => ['type' => 'string', 'label' => 'Описание фотографии'],
                'kicker' => ['type' => 'string', 'label' => 'Надзаголовок'],
                'badge'  => ['type' => 'group', 'label' => 'Метка', 'fields' => $badge],
                'title'  => ['type' => 'string', 'label' => 'Заголовок'],
                'text'   => ['type' => 'string', 'label' => 'Описание'],
                'figure' => ['type' => 'group', 'label' => 'Показатель', 'fields' => [
                    'value'   => ['type' => 'string', 'label' => 'Значение'],
                    'caption' => ['type' => 'string', 'label' => 'Подпись'],
                ]],
                'link' => ['type' => 'group', 'label' => 'Ссылка', 'fields' => [
                    'label' => ['type' => 'string', 'label' => 'Подпись'],
                    'url'   => ['type' => 'page', 'label' => 'Адрес'],
                ]],
            ]],
            'cards' => ['type' => 'list', 'label' => 'Карточки', 'max' => 4, 'of' => [
                'image'  => ['type' => 'image', 'label' => 'Фотография'],
                'alt'    => ['type' => 'string', 'label' => 'Описание фотографии'],
                'kicker' => ['type' => 'string', 'label' => 'Надзаголовок'],
                'badge'  => ['type' => 'group', 'label' => 'Метка', 'fields' => $badge],
                'title'  => ['type' => 'string', 'label' => 'Заголовок'],
                'text'   => ['type' => 'text', 'label' => 'Описание', 'rows' => 2],
                'tags'   => ['type' => 'list', 'label' => 'Пометки', 'of' => 'string'],
                'steps'  => ['type' => 'list', 'label' => 'Нумерованные пометки', 'of' => 'string'],
                'link'   => ['type' => 'group', 'label' => 'Ссылка', 'fields' => [
                    'label' => ['type' => 'string', 'label' => 'Подпись'],
                    'url'   => ['type' => 'page', 'label' => 'Адрес'],
                ]],
            ]],
            'special' => ['type' => 'group', 'label' => 'Специальные версии', 'fields' => [
                'title'    => ['type' => 'string', 'label' => 'Заголовок'],
                'subtitle' => ['type' => 'string', 'label' => 'Подпись рядом'],
                'items'    => ['type' => 'list', 'label' => 'Версии', 'of' => [
                    'image'  => ['type' => 'image', 'label' => 'Фотография'],
                    'alt'    => ['type' => 'string', 'label' => 'Описание фотографии'],
                    'kicker' => ['type' => 'string', 'label' => 'Надзаголовок'],
                    'badge'  => ['type' => 'group', 'label' => 'Метка', 'fields' => $badge],
                    'title'  => ['type' => 'string', 'label' => 'Заголовок'],
                    'text'   => ['type' => 'text', 'label' => 'Описание', 'rows' => 2],
                ]],
            ]],
        ],
    ],

    'gov' => [
        'title'  => 'Госструктурам',
        'group'  => 'Контент',
        'hint'   => 'Три карточки с фотографиями и полоса-призыв под ними.',
        'fields' => $anchor + $heading + [
            'cards' => ['type' => 'list', 'label' => 'Карточки', 'of' => [
                'image'  => ['type' => 'image', 'label' => 'Фотография'],
                'alt'    => ['type' => 'string', 'label' => 'Описание фотографии'],
                'kicker' => ['type' => 'string', 'label' => 'Надзаголовок'],
                'title'  => ['type' => 'string', 'label' => 'Заголовок'],
                'text'   => ['type' => 'text', 'label' => 'Описание', 'rows' => 2],
                'specs'  => ['type' => 'list', 'label' => 'Список через тире', 'of' => 'string'],
                'link'   => ['type' => 'group', 'label' => 'Ссылка', 'fields' => [
                    'label' => ['type' => 'string', 'label' => 'Подпись'],
                    'url'   => ['type' => 'page', 'label' => 'Адрес'],
                ]],
            ]],
            'callout' => ['type' => 'group', 'label' => 'Полоса-призыв', 'fields' => [
                'title'  => ['type' => 'string', 'label' => 'Заголовок'],
                'text'   => ['type' => 'text', 'label' => 'Описание', 'rows' => 2],
                'action' => ['type' => 'group', 'label' => 'Кнопка', 'fields' => $action],
            ]],
        ],
    ],

    'models' => [
        'title'  => 'Модели техники',
        'group'  => 'Контент',
        'hint'   => 'Карточки моделей с таблицей характеристик и кнопками.',
        'fields' => $anchor + [
            'kicker' => ['type' => 'string', 'label' => 'Надзаголовок'],
            'title'  => ['type' => 'string', 'label' => 'Заголовок', 'required' => true],
            'note'   => ['type' => 'string', 'label' => 'Подпись внизу'],
            'items'  => ['type' => 'list', 'label' => 'Модели', 'required' => true, 'of' => [
                'image'    => ['type' => 'image', 'label' => 'Фотография'],
                'alt'      => ['type' => 'string', 'label' => 'Описание фотографии'],
                'title'    => ['type' => 'string', 'label' => 'Название'],
                'url'      => ['type' => 'page', 'label' => 'Адрес страницы модели'],
                'featured' => ['type' => 'bool', 'label' => 'Выделить рамкой'],
                'badges'   => ['type' => 'list', 'label' => 'Метки', 'of' => $badge],
                'specs'    => ['type' => 'list', 'label' => 'Характеристики', 'of' => [
                    'name'  => ['type' => 'string', 'label' => 'Параметр'],
                    'value' => ['type' => 'string', 'label' => 'Значение'],
                ]],
                'actions' => ['type' => 'list', 'label' => 'Кнопки', 'of' => $action, 'max' => 2],
            ]],
        ],
    ],

    'stats' => [
        'title'  => 'Показатели',
        'group'  => 'Контент',
        'hint'   => 'Крупные числа с пояснением. Числа оживают при прокрутке.',
        'fields' => $anchor + $heading + [
            'items' => ['type' => 'list', 'label' => 'Показатели', 'required' => true, 'of' => [
                'value'     => ['type' => 'string', 'label' => 'Значение'],
                'title'     => ['type' => 'string', 'label' => 'Заголовок'],
                'text'      => ['type' => 'text', 'label' => 'Описание', 'rows' => 2],
                'highlight' => ['type' => 'bool', 'label' => 'Выделить'],
            ]],
        ],
    ],

    'service' => [
        'title'  => 'Обслуживание',
        'group'  => 'Контент',
        'hint'   => 'Список с галочками, цитата и картинка справа.',
        'fields' => $anchor + $heading + [
            'points' => ['type' => 'list', 'label' => 'Пункты', 'of' => 'string'],
            'quote'  => ['type' => 'richtext', 'label' => 'Выделенная фраза'],
            'image'  => ['type' => 'image', 'label' => 'Изображение'],
            'alt'    => ['type' => 'string', 'label' => 'Описание изображения'],
        ],
    ],

    /* ============================================================= таблицы */

    'compare' => [
        'title'  => 'Сравнение в две колонки',
        'group'  => 'Таблицы',
        'hint'   => 'KULAGER против альтернативы. На узких экранах листается вбок.',
        'fields' => $anchor + $heading + [
            'columns' => ['type' => 'list', 'label' => 'Названия колонок', 'of' => 'string', 'max' => 2],
            'rows'    => ['type' => 'list', 'label' => 'Строки', 'required' => true, 'of' => [
                'name' => ['type' => 'string', 'label' => 'Параметр'],
                'a'    => ['type' => 'string', 'label' => 'Первая колонка'],
                'b'    => ['type' => 'string', 'label' => 'Вторая колонка'],
            ]],
            'note' => ['type' => 'string', 'label' => 'Примечание под таблицей'],
        ],
    ],

    'spec_compare' => [
        'title'  => 'Сравнение с фотографией',
        'group'  => 'Таблицы',
        'hint'   => 'Таблица на три колонки и фотография рядом.',
        'fields' => $anchor + $heading + [
            'columns' => ['type' => 'list', 'label' => 'Названия колонок', 'of' => 'string', 'max' => 3],
            'rows'    => ['type' => 'list', 'label' => 'Строки', 'required' => true, 'of' => [
                'name' => ['type' => 'string', 'label' => 'Параметр'],
                'a'    => ['type' => 'string', 'label' => 'Вторая колонка'],
                'b'    => ['type' => 'string', 'label' => 'Третья колонка'],
            ]],
            'footnote' => ['type' => 'richtext', 'label' => 'Сноска под таблицей'],
            'image'    => ['type' => 'image', 'label' => 'Фотография'],
            'alt'      => ['type' => 'string', 'label' => 'Описание фотографии'],
        ],
    ],

    'spec_table' => [
        'title'  => 'Таблица «параметр — значение»',
        'group'  => 'Таблицы',
        'hint'   => 'Характеристики или реквизиты. С фотографией — в две колонки, без неё — на всю ширину.',
        'fields' => $anchor + $heading + [
            'rows' => ['type' => 'list', 'label' => 'Строки', 'required' => true, 'of' => [
                'name'  => ['type' => 'string', 'label' => 'Параметр'],
                'value' => ['type' => 'string', 'label' => 'Значение'],
            ]],
            'image' => ['type' => 'image', 'label' => 'Фотография справа'],
            'alt'   => ['type' => 'string', 'label' => 'Описание фотографии'],
            'note'  => ['type' => 'string', 'label' => 'Примечание под таблицей'],
        ],
    ],

    /* ============================================================= призывы */

    'testdrive' => [
        'title'  => 'Полоса с призывом',
        'group'  => 'Призывы',
        'hint'   => 'Узкая полоса на цветном фоне: текст слева, кнопка справа.',
        'fields' => [
            'title'  => ['type' => 'string', 'label' => 'Заголовок', 'required' => true],
            'text'   => ['type' => 'text', 'label' => 'Описание', 'rows' => 2],
            'action' => ['type' => 'group', 'label' => 'Кнопка', 'fields' => $action],
        ],
    ],

    'cta_box' => [
        'title'  => 'Призыв в рамке',
        'group'  => 'Призывы',
        'hint'   => 'Заключительный блок страницы с кнопками.',
        'fields' => $anchor + [
            'title'   => ['type' => 'string', 'label' => 'Заголовок', 'required' => true],
            'text'    => ['type' => 'text', 'label' => 'Описание', 'rows' => 3],
            'actions' => ['type' => 'list', 'label' => 'Кнопки', 'of' => $action, 'max' => 3],
        ],
    ],

    'split_cta' => [
        'title'  => 'Призыв с фотографией',
        'group'  => 'Призывы',
        'hint'   => 'Текст и кнопки слева, фотография справа.',
        'fields' => $anchor + $heading + [
            'image'   => ['type' => 'image', 'label' => 'Фотография', 'required' => true],
            'alt'     => ['type' => 'string', 'label' => 'Описание фотографии'],
            'actions' => ['type' => 'list', 'label' => 'Кнопки', 'of' => $action, 'max' => 3],
        ],
    ],

    /*
     * Правовой документ: политика, оферта, согласие. Не «страница с текстом»,
     * а именно документ — поэтому у него дата редакции и нумерованные разделы,
     * а на странице он выглядит листом, а не частью сайта.
     */
    'legal' => [
        'title'  => 'Правовой документ',
        'group'  => 'Контент',
        'hint'   => 'Политика, оферта, согласие: заголовок, дата редакции и нумерованные разделы. Выглядит листом на белом фоне.',
        'fields' => $anchor + [
            'title'    => ['type' => 'string', 'label' => 'Название документа', 'required' => true],
            'updated'  => ['type' => 'string', 'label' => 'Дата редакции',
                           'hint' => 'Показывается под названием: «Редакция от 22 августа 2026 года».'],
            'intro'    => ['type' => 'richtext', 'label' => 'Вступление',
                           'hint' => 'Короткий абзац перед первым разделом.'],
            'sections' => ['type' => 'list', 'label' => 'Разделы', 'of' => [
                'title' => ['type' => 'string', 'label' => 'Заголовок раздела', 'required' => true],
                'text'  => ['type' => 'richtext', 'label' => 'Текст раздела'],
            ]],
            'footer'   => ['type' => 'richtext', 'label' => 'Заключительная строка',
                           'hint' => 'Например, реквизиты и подпись.'],
        ],
    ],

    'lead_form' => [
        'title'  => 'Форма заявки',
        'group'  => 'Призывы',
        'hint'   => 'Короткая форма: имя, телефон, почта и сообщение. Заявка сохраняется в админке и приходит в телеграм.',
        'fields' => $anchor + [
            'kicker' => ['type' => 'string', 'label' => 'Надзаголовок'],
            'title'  => ['type' => 'string', 'label' => 'Заголовок', 'required' => true],
            'lead'   => ['type' => 'text', 'label' => 'Вводный текст', 'rows' => 3],

            'name_label'    => ['type' => 'string', 'label' => 'Подпись поля «Имя»', 'default' => 'Как к вам обращаться'],
            'phone_label'   => ['type' => 'string', 'label' => 'Подпись поля «Телефон»', 'default' => 'Телефон'],
            'email_label'   => ['type' => 'string', 'label' => 'Подпись поля «Почта»', 'default' => 'Почта'],
            'message_label' => ['type' => 'string', 'label' => 'Подпись поля «Сообщение»', 'default' => 'Что нужно перевозить'],
            'submit'        => ['type' => 'string', 'label' => 'Подпись кнопки', 'default' => 'Отправить заявку'],
            'consent'       => ['type' => 'string', 'label' => 'Текст согласия у галочки',
                                'default' => 'Согласен с политикой обработки персональных данных',
                                'hint' => 'Галочка обязательна: без неё заявка не отправится.'],
            'consent_link'  => ['type' => 'string', 'label' => 'Часть фразы, ставшая ссылкой',
                                'default' => 'политикой обработки персональных данных',
                                'hint' => 'Должна встречаться в тексте выше — иначе ссылки не будет.'],
            'note'          => ['type' => 'text', 'label' => 'Текст под кнопкой', 'rows' => 2],
            'success'       => ['type' => 'text', 'label' => 'Ответ после отправки', 'rows' => 2,
                                'default' => 'Заявка отправлена. Мы свяжемся с вами в рабочее время.'],

            'panel'   => ['type' => 'bool', 'label' => 'На подложке'],
            'actions' => ['type' => 'list', 'label' => 'Кнопки рядом с формой', 'of' => $action, 'max' => 2],
        ],
    ],

    'lead' => [
        'title'  => 'Контакты и заявка',
        'group'  => 'Призывы',
        'hint'   => 'Адреса слева, призыв с кнопками справа.',
        'fields' => $anchor + [
            'kicker'  => ['type' => 'string', 'label' => 'Надзаголовок'],
            'title'   => ['type' => 'string', 'label' => 'Заголовок', 'required' => true],
            'address' => ['type' => 'list', 'label' => 'Адреса', 'of' => [
                'label' => ['type' => 'string', 'label' => 'Подпись'],
                'value' => ['type' => 'richtext', 'label' => 'Адрес'],
            ]],
            'download' => ['type' => 'group', 'label' => 'Кнопка загрузки файла', 'fields' => [
                'label' => ['type' => 'string', 'label' => 'Подпись'],
                'url'   => ['type' => 'url', 'label' => 'Адрес файла'],
            ]],
            'form' => ['type' => 'group', 'label' => 'Правая часть', 'fields' => [
                'title'   => ['type' => 'string', 'label' => 'Заголовок'],
                'text'    => ['type' => 'text', 'label' => 'Описание', 'rows' => 3],
                'actions' => ['type' => 'list', 'label' => 'Кнопки', 'of' => $action, 'max' => 2],
            ]],
        ],
    ],

    /* ============================================================= списки */

    'industries' => [
        'title'  => 'Отрасли применения',
        'group'  => 'Списки',
        'hint'   => 'Колонки ссылок на отрасли. Список берётся из меню сайта, вручную заполнять не нужно.',
        'fields' => $anchor + $heading,
    ],
];

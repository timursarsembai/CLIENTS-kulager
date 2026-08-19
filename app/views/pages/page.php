<?php
declare(strict_types=1);

/**
 * Обычная страница: только список блоков, без своей вёрстки.
 * Подходит для «Отраслей», «О нас», «Контактов», «Реквизитов» и подобных.
 */

/** @var View $view @var array $blocks */

echo $view->blocks($blocks);

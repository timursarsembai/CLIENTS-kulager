<?php
declare(strict_types=1);

/**
 * Цветовые темы. Единственный источник правды: отсюда собирается и CSS
 * стартовой темы, и JSON для переключателя в браузере.
 *
 * swatch — [фон, акцент] для кружка в списке выбора темы.
 */
return [
    'dark' => [
        'name'   => 'Кобальт',
        'swatch' => ['#08131F', '#4DB3F0'],
        'vars'   => [
            '--bg' => '#08131F', '--surface' => '#0C1B2B', '--surface-2' => '#0F2033', '--surface-3' => '#14304B', '--panel' => '#0B1826',
            '--line' => '#1B2E44', '--line-2' => '#24405B', '--line-3' => '#2B4A68', '--line-4' => '#4A6580', '--line-5' => '#6E8FAD',
            '--text' => '#E8EDF2', '--text-strong' => '#ffffff', '--text-2' => '#B7C7D6', '--text-3' => '#93A8BC', '--text-4' => '#6E8598',
            '--text-5' => '#A8BDD0', '--text-6' => '#8AA6BF', '--text-7' => '#8CA6BD', '--nav' => '#9FB3C8',
            '--accent' => '#4DB3F0', '--accent-hover' => '#8ED2FA', '--accent-ink' => '#08131F',
            '--hover-bg' => '#132538', '--hover-bg-2' => '#1B3D5E',
            '--header-bg' => 'rgba(8,19,31,0.98)', '--bar-bg' => 'rgba(8,19,31,0.96)', '--overlay-card' => 'rgba(12,27,43,0.94)',
            '--hero-overlay' => 'linear-gradient(90deg, #08131F 0%, #08131F 30%, rgba(8,19,31,0.9) 50%, rgba(8,19,31,0.6) 74%, rgba(8,19,31,0.28) 100%)',
            '--photo-op' => '0.58', '--logo' => '#4DB3F0', '--hero-scrim' => 'rgba(8,19,31,0.74)',
        ],
    ],
    'light' => [
        'name'   => 'Фарфор',
        'swatch' => ['#F7F9FB', '#1868A8'],
        'vars'   => [
            '--bg' => '#F7F9FB', '--surface' => '#FFFFFF', '--surface-2' => '#FFFFFF', '--surface-3' => '#E7F0F8', '--panel' => '#EFF4F8',
            '--line' => '#DBE4EC', '--line-2' => '#C6D4E0', '--line-3' => '#C6D4E0', '--line-4' => '#A9BCCC', '--line-5' => '#8CA4B8',
            '--text' => '#132433', '--text-strong' => '#08131F', '--text-2' => '#3B546A', '--text-3' => '#546F88', '--text-4' => '#7A93A8',
            '--text-5' => '#3B546A', '--text-6' => '#546F88', '--text-7' => '#5B7488', '--nav' => '#3B546A',
            '--accent' => '#1868A8', '--accent-hover' => '#0F4E82', '--accent-ink' => '#FFFFFF',
            '--hover-bg' => '#E7F0F8', '--hover-bg-2' => '#DCEAF6',
            '--header-bg' => 'rgba(247,249,251,0.98)', '--bar-bg' => 'rgba(255,255,255,0.97)', '--overlay-card' => 'rgba(255,255,255,0.94)',
            '--hero-overlay' => 'linear-gradient(90deg, #F7F9FB 0%, #F7F9FB 30%, rgba(247,249,251,0.9) 50%, rgba(247,249,251,0.6) 74%, rgba(247,249,251,0.28) 100%)',
            '--photo-op' => '0.72', '--logo' => '#1868A8', '--hero-scrim' => 'rgba(247,249,251,0.8)',
        ],
    ],
    'khaki' => [
        'name'   => 'Песок',
        'swatch' => ['#FDFCFA', '#8F470F'],
        'vars'   => [
            '--bg' => '#FDFCFA', '--surface' => '#FFFFFF', '--surface-2' => '#FFFFFF', '--surface-3' => '#F1EFE6', '--panel' => '#F6F4ED',
            '--line' => '#DEDCCF', '--line-2' => '#D8D6C9', '--line-3' => '#C6C3B2', '--line-4' => '#B0AD9B', '--line-5' => '#96937F',
            '--text' => '#17180F', '--text-strong' => '#17180F', '--text-2' => '#4C4E3C', '--text-3' => '#6C6E5B', '--text-4' => '#8C8E7A',
            '--text-5' => '#4C4E3C', '--text-6' => '#6C6E5B', '--text-7' => '#6C6E5B', '--nav' => '#4C4E3C',
            '--accent' => '#8F470F', '--accent-hover' => '#743909', '--accent-ink' => '#FFFFFF',
            '--hover-bg' => '#F1EFE6', '--hover-bg-2' => '#EBE8DC',
            '--header-bg' => 'rgba(253,252,250,0.98)', '--bar-bg' => 'rgba(253,252,250,0.97)', '--overlay-card' => 'rgba(255,255,255,0.94)',
            '--hero-overlay' => 'linear-gradient(90deg, #FDFCFA 0%, #FDFCFA 30%, rgba(253,252,250,0.9) 50%, rgba(253,252,250,0.6) 74%, rgba(253,252,250,0.28) 100%)',
            '--photo-op' => '0.72', '--logo' => '#8F470F', '--hero-scrim' => 'rgba(253,252,250,0.8)',
        ],
    ],
    'graphite' => [
        'name'   => 'Графит',
        'swatch' => ['#141414', '#E8862A'],
        'vars'   => [
            '--bg' => '#141414', '--surface' => '#1C1C1C', '--surface-2' => '#202020', '--surface-3' => '#2C2723', '--panel' => '#181818',
            '--line' => '#2E2E2E', '--line-2' => '#3B3B3B', '--line-3' => '#454545', '--line-4' => '#5A5A5A', '--line-5' => '#727272',
            '--text' => '#EDEBE8', '--text-strong' => '#ffffff', '--text-2' => '#C3BFB9', '--text-3' => '#9A9590', '--text-4' => '#75706B',
            '--text-5' => '#C3BFB9', '--text-6' => '#9A9590', '--text-7' => '#8F8A84', '--nav' => '#9A9590',
            '--accent' => '#E8862A', '--accent-hover' => '#F7A755', '--accent-ink' => '#141414',
            '--hover-bg' => '#242424', '--hover-bg-2' => '#2E2A26',
            '--header-bg' => 'rgba(20,20,20,0.98)', '--bar-bg' => 'rgba(20,20,20,0.96)', '--overlay-card' => 'rgba(28,28,28,0.94)',
            '--hero-overlay' => 'linear-gradient(90deg, #141414 0%, #141414 30%, rgba(20,20,20,0.9) 50%, rgba(20,20,20,0.6) 74%, rgba(20,20,20,0.28) 100%)',
            '--photo-op' => '0.58', '--logo' => '#EDEBE8', '--hero-scrim' => 'rgba(20,20,20,0.74)',
        ],
    ],
    'khakiolive' => [
        'name'   => 'Хаки',
        'swatch' => ['#23271A', '#B9C86E'],
        'vars'   => [
            '--bg' => '#23271A', '--surface' => '#2B3020', '--surface-2' => '#2F3524', '--surface-3' => '#3A4130', '--panel' => '#1E2216',
            '--line' => '#3C4331', '--line-2' => '#4B533D', '--line-3' => '#59624A', '--line-4' => '#6E7859', '--line-5' => '#8A9472',
            '--text' => '#ECEEE0', '--text-strong' => '#FFFFFF', '--text-2' => '#CDD3BB', '--text-3' => '#A8B191', '--text-4' => '#8A9472',
            '--text-5' => '#CDD3BB', '--text-6' => '#A8B191', '--text-7' => '#9AA484', '--nav' => '#A8B191',
            '--accent' => '#B9C86E', '--accent-hover' => '#CBD986', '--accent-ink' => '#1B1F12',
            '--hover-bg' => '#2F3524', '--hover-bg-2' => '#3A4130',
            '--header-bg' => 'rgba(35,39,26,0.98)', '--bar-bg' => 'rgba(30,34,22,0.97)', '--overlay-card' => 'rgba(43,48,32,0.94)',
            '--hero-overlay' => 'linear-gradient(90deg, #23271A 0%, #23271A 30%, rgba(35,39,26,0.9) 50%, rgba(35,39,26,0.6) 74%, rgba(35,39,26,0.28) 100%)',
            '--photo-op' => '0.6', '--logo' => '#B9C86E', '--hero-scrim' => 'rgba(35,39,26,0.78)',
        ],
    ],
];

<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function getSidebarMenus(string $role): array
{
    $menus = require __DIR__ . '/../data/sidebar_menu.php';

    return $menus[$role] ?? [];
}

/**
 * Filters out menu items whose target PHP file does not exist.
 * Keeps parent dropdown items if they still have children after filtering.
 */
function filterExistingMenus(array $menus): array
{
    $filtered = [];

    foreach ($menus as $menu) {
        $hasUrl = isset($menu['url']) && is_string($menu['url']) && $menu['url'] !== '';
        $hasChildren = isset($menu['children']) && is_array($menu['children']);

        if ($hasChildren) {
            $menu['children'] = filterExistingMenus($menu['children']);
            $menu['children'] = array_values($menu['children']);
            $hasChildren = count($menu['children']) > 0;
        }

        $keep = false;
        if ($hasUrl) {
            $keep = sidebar_url_exists((string)$menu['url']);
        } elseif ($hasChildren) {
            $keep = true;
        }

        if ($keep) {
            $filtered[] = $menu;
        }
    }

    return $filtered;
}

function sidebar_url_exists(string $url): bool
{
    // If it's an internal URL like BASE_URL . 'views/...', map it to DOMAIN_PATH.
    $relative = '';
    if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($url, BASE_URL)) {
        $relative = substr($url, strlen(BASE_URL));
    } else {
        $parsed = parse_url($url);
        $relative = is_array($parsed) && isset($parsed['path']) ? ltrim((string)$parsed['path'], '/') : '';
    }

    if ($relative === '') {
        return true;
    }

    $full = rtrim((string)DOMAIN_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return file_exists($full);
}

function markActiveMenus(array $menus, string $currentPath): array
{
    foreach ($menus as &$menu) {

        $menu['active'] = false;

        // SINGLE MENU
        if (isset($menu['url'])) {

            if (str_contains(
                $currentPath,
                basename($menu['url'])
            )) {
                $menu['active'] = true;
            }
        }

        // DROPDOWN MENU
        if (isset($menu['children'])) {

            foreach ($menu['children'] as &$child) {

                $child['active'] = false;

                if (str_contains(
                    $currentPath,
                    basename($child['url'])
                )) {
                    $child['active'] = true;
                    $menu['active'] = true;
                }
            }
        }
    }

    return $menus;
}
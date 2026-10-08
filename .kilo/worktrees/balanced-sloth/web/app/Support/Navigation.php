<?php

/*
 * Composes $navSections for the sidebar from config/navigation.php:
 * filters items by the signed-in user's role, resolves URLs (including the
 * query-string variants like "RIS" → documents?type=RIS), flags the active
 * item for the current request, and marks which keys are starred favorites.
 *
 * Kept as a plain support class so the layout stays readable and any
 * controller (or future API) can reuse the same definition of "what can
 * this user see".
 */

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Facades\Route;

class Navigation
{
    /** One stable identity per destination, used by favorites and recents. */
    public static function key(string $route, array $queries = []): string
    {
        return $queries ? $route.'?'.http_build_query($queries) : $route;
    }

    /**
     * The sections the given user may open, in display order, with URLs and
     * active flags resolved.
     *
     * @param  \App\Models\User|null  $user
     * @return array<string, array<int, array>>
     */
    public static function sectionsFor($user): array
    {
        return [
            'top' => self::resolveItems(config('navigation.top', []), $user),
            'sections' => self::resolveSections(config('navigation.sections', []), $user),
        ];
    }

    /** One section's items, role-filtered, in display order. */
    private static function resolveSections(array $config, $user): array
    {
        $sections = [];

        foreach ($config as $section => $items) {
            $resolved = self::resolveItems($items, $user);

            if ($resolved) {
                $sections[$section] = $resolved;
            }
        }

        return $sections;
    }

    /**
     * Resolve a list of config items: drop unknown routes, drop items the
     * role may not open, fill in the URL and active flag.
     */
    private static function resolveItems(array $items, $user): array
    {
        $role = $user?->role?->name;
        $currentRoute = request()->route()?->getName();
        $currentQuery = request()->query();
        $resolved = [];

        foreach ($items as $item) {
            $route = $item['route'];

            // Unknown or hidden routes are skipped silently: config may
            // name a route a module no longer has.
            if (! Route::has($route)) {
                continue;
            }

            $roles = $item['role'] ?? [Role::ADMIN, Role::INSPECTION_STAFF];
            if (! $role || ! in_array($role, $roles, true)) {
                continue;
            }

            $queries = $item['queries'] ?? [];
            $url = route($route, $queries);
            $key = self::key($route, $queries);

            // A query variant is active only when both the route and the
            // query match exactly; the plain index yields to its variants.
            $active = $currentRoute === $route
                && $queries === array_intersect_key($currentQuery, $queries);

            $resolved[] = [
                'key' => $key,
                'label' => $item['label'],
                'icon' => $item['icon'],
                'url' => $url,
                'route' => $route,
                'keywords' => $item['keywords'] ?? '',
                'fragment' => $item['fragment'] ?? null,
                'active' => $active,
            ];
        }

        return $resolved;
    }

    /** Breadcrumb trail for the current request, newest last. */
    public static function breadcrumbs(): array
    {
        $routeName = request()->route()?->getName();

        // Only the admin panel has breadcrumbs, and every one of its routes is
        // named "admin.<resource>.<action>".
        if (! $routeName || ! str_starts_with($routeName, 'admin.') || str_starts_with($routeName, 'admin.dashboard')) {
            return [];
        }

        $segments = explode('.', $routeName);
        array_shift($segments); // drop the "admin" prefix
        $action = array_pop($segments);
        $resource = implode('.', $segments);

        if ($action === 'index') {
            return [];
        }

        $labels = config('navigation.resource_labels', []);
        $resourceLabel = $labels[$resource]
            ?? \Illuminate\Support\Str::of($resource)->replace('-', ' ')->title()->value();

        $trail = [];

        // Look the index up *with* its admin prefix. The JSON API registers the
        // bare resource names ("documents.index", "equipment.index", …), so an
        // unprefixed lookup would send the crumb to the API endpoint — which
        // answers "Unauthenticated" to a browser.
        $indexRoute = "admin.{$resource}.index";

        if (Route::has($indexRoute)) {
            $trail[] = ['label' => $resourceLabel, 'url' => route($indexRoute)];
        }

        $trail[] = self::leaf($routeName, $action);

        return $trail;
    }

    /** The last breadcrumb: the record's identity or the action's name. */
    private static function leaf(string $routeName, string $action): array
    {
        $route = request()->route();
        $paramName = $route->parameterNames()[0] ?? null;

        $labels = [
            'create' => 'New', 'edit' => 'Edit', 'show' => 'Details',
        ];

        if (in_array($action, ['create', 'edit'], true) || $paramName === null) {
            return ['label' => $labels[$action] ?? \Illuminate\Support\Str::title($action), 'url' => null];
        }

        // The record itself: prefer its human identifier.
        $model = $route->parameter($paramName);
        $label = self::modelLabel($model);

        return ['label' => $label ?: ($labels[$action] ?? $action), 'url' => null];
    }

    private static function modelLabel($model): ?string
    {
        if (! is_object($model)) {
            return null;
        }

        return match (true) {
            // An archive year has no name of its own.
            $model instanceof \App\Models\Archive => "Year {$model->year}",
            // A custodian's folder is named after the person, not their ID.
            $model instanceof \App\Models\Teacher => $model->full_name,
            isset($model->name) => str($model->name)->limit(40)->value(),
            isset($model->document_no) => $model->document_no,
            isset($model->card_no) => $model->card_no,
            isset($model->item_code) => $model->item_code,
            isset($model->employee_id) => "{$model->employee_id} — {$model->full_name}",
            isset($model->code) => $model->code,
            default => null,
        };
    }
}

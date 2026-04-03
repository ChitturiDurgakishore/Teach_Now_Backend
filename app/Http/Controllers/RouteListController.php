<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

class RouteListController extends Controller
{
    public function index(Request $request)
    {
        $methodFilter = $request->query('method'); // GET, POST, etc
        $authFilter   = $request->query('auth');   // yes / no
        $search       = $request->query('search'); // URI search

        $apiRoutes = collect(Route::getRoutes())
            ->filter(fn($route) => str_starts_with($route->uri(), 'api/'))
            ->map(function ($route) {
                return [
                    'method' => implode('|', $route->methods()),
                    'methods_array' => $route->methods(),
                    'uri' => '/' . $route->uri(),
                    'auth_required' => in_array('auth:sanctum', $route->middleware()),
                    'name' => $route->getName(),
                ];
            })
            ->filter(function ($route) use ($methodFilter, $authFilter, $search) {

                // Filter by HTTP method
                if ($methodFilter && !in_array($methodFilter, $route['methods_array'])) {
                    return false;
                }

                // Filter by auth requirement
                if ($authFilter === 'yes' && $route['auth_required'] === false) {
                    return false;
                }

                if ($authFilter === 'no' && $route['auth_required'] === true) {
                    return false;
                }

                // Filter by URI search
                if ($search && stripos($route['uri'], $search) === false) {
                    return false;
                }

                return true;
            })
            ->values();

        return view('api-routes', [
            'baseUrl'      => url('/'),
            'totalRoutes'  => $apiRoutes->count(),
            'routes'       => $apiRoutes,
            'methodFilter' => $methodFilter,
            'authFilter'   => $authFilter,
            'search'       => $search,
        ]);
    }
}

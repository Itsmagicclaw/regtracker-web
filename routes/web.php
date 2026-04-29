<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index']);
Route::get('/api/clear-cache', [DashboardController::class, 'clearCache']);

Route::get('/debug', function () {
    $info = [
        'php'        => PHP_VERSION,
        'laravel'    => app()->version(),
        'env'        => app()->environment(),
        'cache_dir'  => storage_path('framework/cache/data'),
        'cache_ok'   => is_writable(storage_path('framework/cache/data')),
        'storage_ok' => is_writable(storage_path()),
        'app_key_set'=> !empty(config('app.key')),
    ];
    try {
        $fetcher = new \App\Services\RegulatoryFetcher();
        $sources = $fetcher->getSources();
        $info['sources_count'] = count($sources);
        // Try fetching just one source
        $results = $fetcher->fetchAll('UK');
        $info['fetch_ok'] = true;
        $info['uk_items'] = count($results[0]['items'] ?? []);
    } catch (\Throwable $e) {
        $info['fetch_error'] = $e->getMessage();
        $info['fetch_trace'] = $e->getTraceAsString();
    }
    return response()->json($info, 200, [], JSON_PRETTY_PRINT);
});

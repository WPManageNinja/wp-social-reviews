<?php

defined('ABSPATH') or die;

/**
 * @var \WPSocialReviews\Framework\Http\Router $router
 */

use WPSocialReviews\App\Modules\SystemTools\SystemToolsController;

$router->prefix('system-tools')->withPolicy('SystemToolsPolicy')->group(function ($router) {
    $router->get('/', [SystemToolsController::class, 'index']);
    $router->get('/rest-check', [SystemToolsController::class, 'restCheck']);
    $router->post('/rest-check', [SystemToolsController::class, 'restCheck']);
    $router->put('/rest-check', [SystemToolsController::class, 'restCheck']);
    $router->delete('/rest-check', [SystemToolsController::class, 'restCheck']);

    $router->get('/cron', [SystemToolsController::class, 'getCron']);
    $router->post('/cron/run', [SystemToolsController::class, 'runCron']);
    $router->post('/cron/reschedule', [SystemToolsController::class, 'rescheduleCrons']);

    $router->get('/platforms', [SystemToolsController::class, 'getPlatforms']);
    $router->get('/database', [SystemToolsController::class, 'getDatabase']);
});

<?php
/** Minimal faithful Phase 24 copy of four guarded category declarations from app/Http/Routes/api.php. */
$router->prefix('reviews')->withPolicy('CategoryManagementPolicy')->group(function ($router) {
    $router->put('categories/{id}', 'CategoriesController@update');
    $router->delete('categories/{id}', 'CategoriesController@delete');
});
$router->prefix('testimonials')->withPolicy('CategoryManagementPolicy')->group(function ($router) {
    $router->put('categories/{id}', 'CategoriesController@update');
    $router->delete('categories/{id}', 'CategoriesController@delete');
});

<?php
// routes/web.php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->get('/', function () use ($router) {
    return $router->app->version();
});

// Public routes
$router->post('/login', 'AuthController@login');

// Protected routes
$router->group(['middleware' => 'auth'], function () use ($router) {
    // Auth
    $router->get('/me', 'AuthController@me');
    $router->post('/logout', 'AuthController@logout');
    
    // Products
    $router->get('/products', 'ProductController@index');
    $router->get('/products/{id}', 'ProductController@show');
    
    // Products - Admin dan Manager bisa create/update/delete
    $router->group(['middleware' => 'role:admin,manager'], function () use ($router) {
        $router->post('/products', 'ProductController@store');
        $router->put('/products/{id}', 'ProductController@update');
        $router->delete('/products/{id}', 'ProductController@destroy');
    });
    
    // Donations
    $router->get('/donations/stats', 'DonationController@getStats');
    $router->get('/donations', 'DonationController@index');
    $router->post('/donations', 'DonationController@store');
    $router->get('/donations/{id}', 'DonationController@show');
    
    // CAMPAIGNS - SEMUA BISA VIEW
    $router->get('/campaigns/stats', 'CampaignController@getStats');
    $router->get('/campaigns/categories', 'CampaignController@getCategories');
    $router->get('/campaigns', 'CampaignController@index');
    $router->get('/campaigns/{id}', 'CampaignController@show');
    
    // CAMPAIGNS - ADMIN DAN MANAGER BISA CREATE/UPDATE/DELETE
    $router->group(['middleware' => 'role:admin,manager'], function () use ($router) {
        $router->post('/campaigns', 'CampaignController@store');
        $router->put('/campaigns/{id}', 'CampaignController@update');
        $router->delete('/campaigns/{id}', 'CampaignController@destroy');
        $router->patch('/campaigns/{id}/toggle-status', 'CampaignController@toggleStatus');
    });
    
    // USER MANAGEMENT - HANYA ADMIN
    $router->group(['middleware' => 'role:admin'], function () use ($router) {
        $router->get('/users', 'UserController@index');
        $router->post('/users', 'UserController@store');
        $router->get('/users/{id}', 'UserController@show');
        $router->put('/users/{id}', 'UserController@update');
        $router->delete('/users/{id}', 'UserController@destroy');
        $router->get('/roles', 'UserController@getRoles');
        $router->get('/permissions', 'UserController@getPermissions');
        $router->post('/users/{id}/assign-role', 'UserController@assignRole');
        $router->post('/users/{id}/assign-permissions', 'UserController@assignPermissions');
    });
});
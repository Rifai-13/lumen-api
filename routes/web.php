<?php
// routes/web.php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->get('/', function () use ($router) {
    return $router->app->version();
});

// Public routes
$router->post('/login', 'AuthController@login');
$router->get('/public/campaigns', 'CampaignController@publicIndex');

// Protected routes
$router->group(['middleware' => 'auth'], function () use ($router) {
    
    // Auth
    $router->get('/me', 'AuthController@me');
    $router->post('/logout', 'AuthController@logout');
    
    // Donations
    $router->get('/donations/stats', 'DonationController@getStats');
    $router->get('/donations', 'DonationController@index');
    $router->post('/donations', 'DonationController@store');
    
    // Campaigns - semua user bisa lihat
    $router->get('/campaigns', 'CampaignController@index');
    $router->get('/campaigns/{id}', 'CampaignController@show');
    
    // Campaigns - hanya admin/manager yang bisa edit
    $router->group(['middleware' => 'role:admin,manager'], function () use ($router) {
        $router->post('/campaigns', 'CampaignController@store');
        $router->post('/campaigns/{id}', 'CampaignController@update');
        $router->delete('/campaigns/{id}', 'CampaignController@destroy');
    });
    
    // ============= USER MANAGEMENT =============
    // Hanya admin yang bisa manage users
    $router->group(['middleware' => 'role:admin'], function () use ($router) {
        
        // User CRUD
        $router->get('/users', 'UserController@index');
        $router->post('/users', 'UserController@store');
        $router->get('/users/{id}', 'UserController@show');
        $router->put('/users/{id}', 'UserController@update');
        $router->delete('/users/{id}', 'UserController@destroy');
        
        // Get user permissions
        $router->get('/users/{id}/permissions', 'UserController@getUserPermissions');
        
        // Roles & Permissions (for dropdown)
        $router->get('/roles', 'UserController@getRoles');
        $router->get('/permissions', 'UserController@getPermissions');
        
        // Assign role & permissions
        $router->post('/users/{id}/assign-role', 'UserController@assignRole');
        $router->post('/users/{id}/assign-permissions', 'UserController@assignPermissions');
    });
});
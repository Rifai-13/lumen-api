<?php
// routes/web.php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->get('/', function () use ($router) {
    return $router->app->version();
});

// ============= API ROUTES =============
$router->group(['prefix' => 'api'], function () use ($router) {
    
    // Public routes (no auth required)
    $router->post('/login', 'AuthController@login');

    // ============= PUBLIC API ROUTES =============
    $router->group(['prefix' => 'public'], function () use ($router) {
        // Public campaigns
        $router->get('/campaigns', 'CampaignController@publicIndex');
        
        // Public donations
        $router->post('/donations', 'PublicDonationController@store');
    });

    // ============= PAYMENT ROUTES (Public) =============
    $router->group(['prefix' => 'payment'], function () use ($router) {
        $router->post('/create-donation', 'PaymentController@createDonation');
        $router->get('/status/{externalId}', 'PaymentController@checkPaymentStatus');
        $router->get('/methods', 'PaymentController@getAvailablePaymentMethods');
        $router->post('/callback', 'PaymentController@handleXenditCallback');
    });

    // ============= PROTECTED ROUTES (Require Auth) =============
    $router->group(['middleware' => 'auth'], function () use ($router) {
        
        // Auth
        $router->get('/me', 'AuthController@me');
        $router->post('/logout', 'AuthController@logout');
        
        // Donations
        $router->get('/donations/stats', 'DonationController@getStats');
        $router->get('/donations', 'DonationController@index');
        $router->post('/donations', 'DonationController@store');
        
        // CAMPAIGNS
        $router->get('/campaigns', 'CampaignController@index');
        $router->get('/campaigns/{id}', 'CampaignController@show');
        $router->post('/campaigns', 'CampaignController@store');
        $router->post('/campaigns/{id}', 'CampaignController@update');
        $router->delete('/campaigns/{id}', 'CampaignController@destroy');
        
        // User CRUD
        $router->get('/users', 'UserController@index');
        $router->post('/users', 'UserController@store');
        $router->get('/users/{id}', 'UserController@show');
        $router->put('/users/{id}', 'UserController@update');
        $router->get('/roles', 'UserController@getRoles');
        
        // Admin only
        $router->group(['middleware' => 'role:admin'], function () use ($router) {
            $router->get('/users/{id}/permissions', 'UserController@getUserPermissions');
            $router->get('/permissions', 'UserController@getPermissions');
            $router->delete('/users/{id}', 'UserController@destroy');
            $router->post('/users/{id}/assign-role', 'UserController@assignRole');
            $router->post('/users/{id}/assign-permissions', 'UserController@assignPermissions');
        });
    });
});
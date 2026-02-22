<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

// Public routes
$router->post('/login', 'AuthController@login');
$router->post('/register', 'AuthController@register');

// Protected routes
$router->group(['middleware' => 'auth'], function () use ($router) {
    
    // User routes
    $router->get('/user', 'AuthController@user');
    $router->post('/logout', 'AuthController@logout');
    $router->get('/products', 'ProductController@index');
    $router->get('/products/{id}', 'ProductController@show');
    
    // Role-based routes (only for manager)
    $router->group(['middleware' => 'role:manager'], function () use ($router) {
        $router->post('/products', 'ProductController@store');
        $router->put('/products/{id}', 'ProductController@update');
        $router->delete('/products/{id}', 'ProductController@destroy');
    });
});
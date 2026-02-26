<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Auth::login');
$routes->get('/login', 'Auth::login');
$routes->post('/login', 'Auth::doLogin');
$routes->get('/logout', 'Auth::logout');
$routes->get('/register', 'Auth::register');
$routes->post('/register', 'Auth::doRegister');
$routes->get('/dashboard', 'Clients::index');
$routes->get('/clients', 'Clients::index');
$routes->get('/clients/(:num)', 'Clients::view/$1');
$routes->get('/comparison', 'Comparison::index');

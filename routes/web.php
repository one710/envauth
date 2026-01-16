<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseFactoryInterface;
use Tez\Router;

/**
 * Register application routes.
 *
 * @param  Router  $router
 * @return void
 */
return function (Router $router): void {

    $router->route('/', 'HomeController@index');

    $router->route('/health', function ($request, ResponseFactoryInterface $responseFactory) {
        $response = $responseFactory->createResponse();
        $response->getBody()->write('OK');

        return $response;
    });

    $router->group('/api', function (Router $router) {

        // License verification endpoints
        $router->route('/license/verify', 'LicenseVerificationController@verify', ['POST']);
    });

    // License reset page (web interface)
    $router->route('/license/reset', 'LicenseController@showResetPage', ['GET']);
    $router->route('/license/reset', 'LicenseController@handleReset', ['POST']);

    // OAuth endpoints
    $router->route('/oauth/login', 'OAuthController@login', ['GET']);
    $router->route('/oauth/callback', 'OAuthController@callback', ['GET']);
    $router->route('/oauth/logout', 'OAuthController@logout', ['GET']);
};

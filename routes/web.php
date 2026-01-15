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

    $router->route('/error', function () {
        throw new \Exception('Test error/exception handling');
    });

    $router->group('/api', function (Router $router) {

        $router->route('/status', function ($request, ResponseFactoryInterface $responseFactory) {
            $response = $responseFactory->createResponse();
            $response->getBody()->write('OK');

            return $response;
        }, ['GET']);
    });
};

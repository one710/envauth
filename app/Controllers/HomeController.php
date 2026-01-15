<?php

declare(strict_types=1);

namespace App\Controllers;

use Phast\Controller;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeController extends Controller
{
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return $this->render('welcome', [
            'message' => 'Hello from HomeController',
        ]);
    }
}

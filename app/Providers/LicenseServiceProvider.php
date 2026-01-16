<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\LicenseVerificationService;
use App\Services\OAuthService;
use Katora\Container;
use Kunfig\ConfigInterface;
use Phast\Providers\ProviderInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class LicenseServiceProvider implements ProviderInterface
{
    public function provide(Container $container): void
    {
        // Register LicenseVerificationService
        $container->set(LicenseVerificationService::class, function (Container $c) {
            return new LicenseVerificationService(
                $c->get(LoggerInterface::class),
                $c->get(ClientInterface::class),
                $c->get(RequestFactoryInterface::class),
                $c->get(ConfigInterface::class),
                $c->get(OAuthService::class),
                $c->get(ClockInterface::class)
            );
        });

        // Register OAuthService
        $container->set(OAuthService::class, function (Container $c) {
            $config = $c->get(ConfigInterface::class);

            return new OAuthService(
                $c->get(ClientInterface::class),
                $c->get(RequestFactoryInterface::class),
                $c->get(StreamFactoryInterface::class),
                $c->get(LoggerInterface::class),
                $c->get(ClockInterface::class),
                $config->get('envato.oauth.client_id', ''),
                $config->get('envato.oauth.client_secret', ''),
                $config->get('envato.oauth.redirect_uri', '')
            );
        });
    }

    public function init(Container $container): void
    {
        // No initialization needed
    }
}

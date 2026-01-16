<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\OAuthService;
use Phast\Controller;
use Phast\Exception\HttpException;
use Phlash\FlashInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class OAuthController extends Controller
{
    public function __construct(
        private readonly OAuthService $oauthService,
        private readonly LoggerInterface $logger,
        private readonly FlashInterface $flash
    ) {}

    /**
     * Initiate OAuth login
     * GET /oauth/login
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $returnUrl = $queryParams['return_url'] ?? '/license/reset';

        // Generate state token for CSRF protection
        $state = bin2hex(random_bytes(32));

        // Store state in session
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_return_url'] = $returnUrl;

        // Session will be automatically saved by PHP at end of script
        // No need to manually close/start

        $authUrl = $this->oauthService->getAuthorizationUrl($state);

        return $this->redirect($authUrl);
    }

    /**
     * OAuth callback
     * GET /oauth/callback
     */
    public function callback(ServerRequestInterface $request): ResponseInterface
    {
        // Get query parameters - try both methods
        $queryParams = $request->getQueryParams();

        // Fallback: parse from URI if getQueryParams() doesn't work
        if (empty($queryParams) || (! isset($queryParams['code']) && ! isset($queryParams['state']))) {
            $uri = $request->getUri();
            $queryString = $uri->getQuery();
            if ($queryString) {
                parse_str($queryString, $queryParams);
            }
        }

        $code = $queryParams['code'] ?? null;
        $state = $queryParams['state'] ?? null;
        $error = $queryParams['error'] ?? null;

        $storedState = $_SESSION['oauth_state'] ?? null;
        $returnUrl = $_SESSION['oauth_return_url'] ?? '/license/reset';

        // Check for errors
        if ($error) {
            $this->logger->error('OAuth error in callback', ['error' => $error]);
            throw new HttpException(400, 'OAuth error: '.$error);
        }

        // Verify state parameter
        if (! $state) {
            $this->logger->error('State parameter missing from OAuth callback', [
                'request_uri' => (string) $request->getUri(),
                'query_params' => $queryParams,
            ]);
            throw new HttpException(400, 'State parameter is missing from OAuth callback.');
        }

        // Verify state matches stored value (CSRF protection)
        if (! $storedState) {
            $this->logger->error('Stored state missing from session during OAuth callback', [
                'session_id' => session_id() ?: 'none',
                'received_state' => $state,
            ]);
            throw new HttpException(400, 'OAuth state not found in session. Please try logging in again.');
        }

        if ($state !== $storedState) {
            $this->logger->error('OAuth state mismatch', [
                'received_state' => $state,
                'stored_state' => $storedState,
                'session_id' => session_id() ?: 'none',
            ]);
            throw new HttpException(400, 'Invalid state parameter. Possible CSRF attack.');
        }

        // Check for authorization code
        if (! $code) {
            $this->logger->error('Authorization code missing from OAuth callback', [
                'request_uri' => (string) $request->getUri(),
                'query_params' => $queryParams,
            ]);
            throw new HttpException(400, 'Authorization code is missing');
        }

        // Exchange code for token
        $tokenResult = $this->oauthService->exchangeCodeForToken($code);

        if (! $tokenResult['success']) {
            $this->logger->error('OAuth token exchange failed', [
                'error_message' => $tokenResult['error'] ?? 'Unknown error',
                'token_result' => $tokenResult,
            ]);
            throw new HttpException(400, $tokenResult['error'] ?? 'Failed to authenticate');
        }

        // Check if access token was returned
        $accessToken = $tokenResult['access_token'] ?? null;
        if (! $accessToken) {
            $this->logger->error('OAuth token exchange returned no access token', [
                'response' => $tokenResult,
            ]);

            // Return more detailed error for debugging
            $errorMessage = $tokenResult['error'] ?? 'Access token not received from OAuth provider';
            if (isset($tokenResult['error']) && is_string($tokenResult['error']) && strlen($tokenResult['error']) < 200) {
                $errorMessage = $tokenResult['error'];
            }

            throw new HttpException(400, $errorMessage);
        }

        // Get user information
        $userInfoResult = $this->oauthService->getUserInfo($accessToken);

        if (! $userInfoResult['success']) {
            $this->logger->error('Failed to get user information from Envato API', [
                'error_message' => $userInfoResult['error'] ?? 'Unknown error',
                'user_info_result' => $userInfoResult,
            ]);
            throw new HttpException(400, $userInfoResult['error'] ?? 'Failed to get user information');
        }

        // Save OAuth user
        $oauthUser = $this->oauthService->saveOAuthUser(
            $userInfoResult['user'],
            $tokenResult['access_token'],
            $tokenResult['refresh_token'] ?? null,
            $tokenResult['expires_in'] ?? null
        );

        // Store user ID in session
        $_SESSION['oauth_user_id'] = $oauthUser->id;
        $_SESSION['oauth_username'] = $oauthUser->username;
        unset($_SESSION['oauth_state']);
        unset($_SESSION['oauth_return_url']);

        // Redirect to return URL or show success
        return $this->redirect($returnUrl);
    }

    /**
     * Logout
     * GET /oauth/logout
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        unset($_SESSION['oauth_user_id']);
        unset($_SESSION['oauth_username']);

        $this->flash->flashNow('success', 'Logged out successfully');

        return $this->redirect('/');
    }
}

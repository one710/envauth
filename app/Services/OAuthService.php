<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OAuthUser;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class OAuthService
{
    private const ENVATO_AUTHORIZATION_URL = 'https://api.envato.com/authorization';

    private const ENVATO_TOKEN_URL = 'https://api.envato.com/token';

    private const ENVATO_WHOAMI_URL = 'https://api.envato.com/whoami';

    private const ENVATO_ACCOUNT_URL = 'https://api.envato.com/v1/market/private/user/account.json';

    private const ENVATO_USERNAME_URL = 'https://api.envato.com/v1/market/private/user/username.json';

    private const ENVATO_EMAIL_URL = 'https://api.envato.com/v1/market/private/user/email.json';

    private const ENVATO_PURCHASE_VERIFY_URL = 'https://api.envato.com/v3/market/author/sale';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri
    ) {}

    /**
     * Get OAuth authorization URL
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'scope' => 'default', // Request default scopes configured in OAuth app
        ];

        return self::ENVATO_AUTHORIZATION_URL.'?'.http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     *
     * @return array{success: bool, access_token?: string, refresh_token?: string, expires_in?: int, error?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        try {
            $body = $this->streamFactory->createStream(http_build_query([
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ]));

            $request = $this->requestFactory->createRequest('POST', self::ENVATO_TOKEN_URL)
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody($body);

            $response = $this->httpClient->sendRequest($request);
            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $data = json_decode($responseBody, true);

            // Log raw response for debugging
            error_log('OAuth token exchange - Status: '.$statusCode);
            error_log('OAuth token exchange - Response body length: '.strlen($responseBody));
            error_log('OAuth token exchange - Response body: '.substr($responseBody, 0, 1000));
            if ($data) {
                error_log('OAuth token exchange - Parsed keys: '.implode(', ', array_keys($data)));
            }

            // Log the full response for debugging
            $this->logger->info('OAuth token exchange response', [
                'status' => $statusCode,
                'response' => $data,
                'raw_response' => $responseBody,
                'response_length' => strlen($responseBody),
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('OAuth token exchange failed', [
                    'status' => $response->getStatusCode(),
                    'response' => $data,
                    'raw_response' => $responseBody,
                ]);

                return [
                    'success' => false,
                    'error' => $data['error_description'] ?? $data['error'] ?? 'Failed to exchange code for token',
                ];
            }

            // Check if access_token exists
            if (! isset($data['access_token'])) {
                $this->logger->error('OAuth token exchange response missing access_token', [
                    'response' => $data,
                    'raw_response' => $responseBody,
                    'response_keys' => array_keys($data ?? []),
                ]);

                return [
                    'success' => false,
                    'error' => 'Access token not found in response. Response: '.substr($responseBody, 0, 500),
                ];
            }

            return [
                'success' => true,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_in' => $data['expires_in'] ?? null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('OAuth token exchange exception', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to exchange code for token: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get user information from Envato API
     *
     * @return array{success: bool, user?: array, error?: string}
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            // Use the /whoami endpoint to get user ID
            $whoamiRequest = $this->requestFactory->createRequest('GET', self::ENVATO_WHOAMI_URL)
                ->withHeader('Authorization', 'Bearer '.$accessToken);

            $whoamiResponse = $this->httpClient->sendRequest($whoamiRequest);
            $whoamiBody = (string) $whoamiResponse->getBody();
            $whoamiData = json_decode($whoamiBody, true);

            if ($whoamiResponse->getStatusCode() !== 200) {
                $this->logger->error('Failed to get user ID from /whoami endpoint', [
                    'status' => $whoamiResponse->getStatusCode(),
                    'response' => $whoamiData,
                    'raw_response' => $whoamiBody,
                ]);

                return [
                    'success' => false,
                    'error' => $whoamiData['error'] ?? $whoamiData['message'] ?? 'Failed to get user information',
                ];
            }

            $userId = $whoamiData['userId'] ?? null;
            if (! $userId) {
                return [
                    'success' => false,
                    'error' => 'User ID not found in response',
                ];
            }

            // Fetch account details
            $accountData = null;
            try {
                $accountRequest = $this->requestFactory->createRequest('GET', self::ENVATO_ACCOUNT_URL)
                    ->withHeader('Authorization', 'Bearer '.$accessToken);

                $accountResponse = $this->httpClient->sendRequest($accountRequest);
                $accountBody = (string) $accountResponse->getBody();
                $accountData = json_decode($accountBody, true);

                if ($accountResponse->getStatusCode() !== 200) {
                    $this->logger->warning('Failed to get account details from Envato', [
                        'status' => $accountResponse->getStatusCode(),
                        'response' => $accountData,
                        'raw_response' => $accountBody,
                    ]);
                    $accountData = null;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Exception fetching account details', [
                    'exception' => $e->getMessage(),
                ]);
                $accountData = null;
            }

            // Fetch username
            $username = null;
            try {
                $usernameRequest = $this->requestFactory->createRequest('GET', self::ENVATO_USERNAME_URL)
                    ->withHeader('Authorization', 'Bearer '.$accessToken);

                $usernameResponse = $this->httpClient->sendRequest($usernameRequest);
                $usernameBody = (string) $usernameResponse->getBody();
                $usernameData = json_decode($usernameBody, true);

                if ($usernameResponse->getStatusCode() === 200 && isset($usernameData['username'])) {
                    $username = $usernameData['username'];
                } else {
                    $this->logger->warning('Failed to get username from Envato', [
                        'status' => $usernameResponse->getStatusCode(),
                        'response' => $usernameData,
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Exception fetching username', [
                    'exception' => $e->getMessage(),
                ]);
            }

            // Fetch email
            $email = null;
            try {
                $emailRequest = $this->requestFactory->createRequest('GET', self::ENVATO_EMAIL_URL)
                    ->withHeader('Authorization', 'Bearer '.$accessToken);

                $emailResponse = $this->httpClient->sendRequest($emailRequest);
                $emailBody = (string) $emailResponse->getBody();
                $emailData = json_decode($emailBody, true);

                if ($emailResponse->getStatusCode() === 200 && isset($emailData['email'])) {
                    $email = $emailData['email'];
                } else {
                    $this->logger->warning('Failed to get email from Envato', [
                        'status' => $emailResponse->getStatusCode(),
                        'response' => $emailData,
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Exception fetching email', [
                    'exception' => $e->getMessage(),
                ]);
            }

            // Combine all data
            $userData = [
                'id' => $userId,
                'user_id' => $userId,
                'username' => $username ?? $accountData['account']['username'] ?? $accountData['account']['firstname'] ?? 'user_'.$userId,
                'email' => $email ?? $accountData['account']['email'] ?? null,
            ];

            // Add account data if available
            if ($accountData && isset($accountData['account'])) {
                $userData['firstname'] = $accountData['account']['firstname'] ?? null;
                $userData['surname'] = $accountData['account']['surname'] ?? null;
                $userData['image'] = $accountData['account']['image'] ?? null;
                $userData['country'] = $accountData['account']['country'] ?? null;
                // Merge any other account fields
                $userData = array_merge($userData, $accountData['account']);
            }

            return [
                'success' => true,
                'user' => $userData,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Get user info exception', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get user information: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Save or update OAuth user
     */
    public function saveOAuthUser(array $userData, string $accessToken, ?string $refreshToken = null, ?int $expiresIn = null): OAuthUser
    {
        $envatoUserId = (string) ($userData['id'] ?? $userData['user_id'] ?? '');
        $username = $userData['username'] ?? '';
        $email = $userData['email'] ?? null;

        $oauthUser = OAuthUser::where(['envato_user_id' => $envatoUserId])->first();

        if (! $oauthUser) {
            $oauthUser = new OAuthUser;
            $oauthUser->envato_user_id = $envatoUserId;
            $oauthUser->username = $username;
            $oauthUser->email = $email;
            $oauthUser->created_at = $this->clock->now()->format('Y-m-d H:i:s');
        }

        $oauthUser->access_token = $accessToken;
        $oauthUser->refresh_token = $refreshToken;

        if ($expiresIn) {
            $oauthUser->token_expires_at = $this->clock->now()->modify("+{$expiresIn} seconds")->format('Y-m-d H:i:s');
        }

        $oauthUser->updated_at = $this->clock->now()->format('Y-m-d H:i:s');
        $oauthUser->save();

        return $oauthUser;
    }

    /**
     * Get OAuth user by session or token
     */
    public function getCurrentUser(?string $sessionUserId = null): ?OAuthUser
    {
        if ($sessionUserId) {
            return OAuthUser::find((int) $sessionUserId);
        }

        return null;
    }
}

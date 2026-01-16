<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidItemIdException;
use App\Exceptions\InvalidProductTypeException;
use App\Exceptions\IpAddressRequiredException;
use App\Exceptions\LicenseAlreadyActivatedException;
use App\Exceptions\LicenseInactiveException;
use App\Exceptions\LicenseNotFoundException;
use App\Exceptions\MachineIdRequiredException;
use App\Exceptions\PurchaseVerificationFailedException;
use App\Models\Activation;
use App\Models\License;
use App\Models\LicenseReset;
use App\Models\OAuthUser;
use Kunfig\ConfigInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

class LicenseVerificationService
{
    private const ENVATO_PURCHASE_VERIFY_URL = 'https://api.envato.com/v3/market/author/sale';

    private const ENVATO_BUYER_PURCHASE_URL = 'https://api.envato.com/v3/market/buyer/purchase';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly ConfigInterface $config,
        private readonly OAuthService $oauthService,
        private readonly ClockInterface $clock
    ) {}

    /**
     * Verify purchase code with Envato and activate license
     *
     * @param  string  $purchaseCode  Envato purchase code
     * @param  int|string  $itemId  Envato item ID
     * @param  string|null  $machineId  Machine ID (for machine_id type)
     * @param  string|null  $ipAddress  IP address (for ip_address type, auto-detected if not provided)
     * @return true Returns true on successful verification/activation
     *
     * @throws InvalidItemIdException
     * @throws PurchaseVerificationFailedException
     * @throws InvalidProductTypeException
     * @throws MachineIdRequiredException
     * @throws IpAddressRequiredException
     * @throws LicenseAlreadyActivatedException
     */
    public function verify(string $purchaseCode, int|string $itemId, ?string $machineId = null, ?string $ipAddress = null): bool
    {
        $itemIdStr = (string) $itemId;

        // Check if item ID is in the allowed mapping
        $items = $this->config->get('envato.items', []);
        if (! isset($items[$itemIdStr])) {
            $this->logger->warning('License verification failed: Item ID not in allowed mapping', [
                'item_id' => $itemIdStr,
                'purchase_code' => $purchaseCode,
            ]);

            throw new InvalidItemIdException('Item ID is not allowed');
        }

        $verificationType = $items[$itemIdStr];
        if (! in_array($verificationType, ['machine_id', 'ip_address'], true)) {
            throw new InvalidProductTypeException('Invalid verification type for item ID');
        }

        // Verify purchase code with Envato API
        $purchaseData = $this->verifyPurchaseCode($purchaseCode);

        // Verify item ID matches
        $purchaseItemId = (string) ($purchaseData['item']['id'] ?? $purchaseData['item_id'] ?? '');
        if ($purchaseItemId !== $itemIdStr) {
            $this->logger->warning('License verification failed: Item ID mismatch', [
                'purchase_code' => $purchaseCode,
                'expected_item_id' => $itemIdStr,
                'purchase_item_id' => $purchaseItemId,
            ]);

            throw new PurchaseVerificationFailedException('Purchase code does not match the provided item ID');
        }

        // Find or create license record
        $license = License::where(['envato_purchase_code' => $purchaseCode])->first();

        if (! $license) {
            $license = new License;
            $license->envato_purchase_code = $purchaseCode;
            $license->envato_item_id = (int) $itemIdStr;
            $license->product_type = $verificationType;
            $license->is_active = true;
            $license->created_at = $this->clock->now()->format('Y-m-d H:i:s');
            $license->updated_at = $this->clock->now()->format('Y-m-d H:i:s');
            $license->save();

            $this->logger->info('License created from purchase code', [
                'license_id' => $license->id,
                'purchase_code' => $purchaseCode,
                'item_id' => $itemIdStr,
            ]);
        } else {
            // Update item ID and product type if they changed
            if ($license->envato_item_id !== (int) $itemIdStr || $license->product_type !== $verificationType) {
                $license->envato_item_id = (int) $itemIdStr;
                $license->product_type = $verificationType;
                $license->updated_at = $this->clock->now()->format('Y-m-d H:i:s');
                $license->save();
            }

            if (! $license->is_active) {
                throw new LicenseInactiveException('License is inactive');
            }
        }

        // Activate based on verification type
        if ($verificationType === 'machine_id') {
            $this->activateMachineIdLicense($license, $machineId);
        } else {
            $this->activateIpAddressLicense($license, $ipAddress);
        }

        return true;
    }

    /**
     * Verify purchase code with Envato API
     *
     * @return array Purchase data from Envato
     *
     * @throws PurchaseVerificationFailedException
     */
    private function verifyPurchaseCode(string $purchaseCode): array
    {
        $personalToken = $this->config->get('envato.personal_token', '');

        if (! $personalToken) {
            $this->logger->error('Cannot verify purchase: Personal token not configured');
            throw new PurchaseVerificationFailedException('Purchase verification not configured');
        }

        try {
            $url = self::ENVATO_PURCHASE_VERIFY_URL.'?code='.urlencode($purchaseCode);

            $this->logger->debug('Verifying purchase code with Envato API', [
                'url' => $url,
                'purchase_code' => $purchaseCode,
                'token_length' => strlen($personalToken),
            ]);

            $request = $this->requestFactory->createRequest('GET', $url)
                ->withHeader('Authorization', 'Bearer '.$personalToken);

            $response = $this->httpClient->sendRequest($request);
            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $data = json_decode($responseBody, true);

            $this->logger->debug('Envato API response', [
                'status' => $statusCode,
                'response_keys' => array_keys($data ?? []),
                'response_preview' => substr($responseBody, 0, 500),
            ]);

            if ($statusCode !== 200) {
                $errorMessage = $data['error'] ?? $data['message'] ?? 'Unknown error';
                $errorDescription = $data['error_description'] ?? $data['description'] ?? null;

                $this->logger->error('Purchase verification failed', [
                    'status' => $statusCode,
                    'response' => $data,
                    'raw_response' => $responseBody,
                    'purchase_code' => $purchaseCode,
                ]);

                $message = 'Failed to verify purchase code with Envato';
                if ($statusCode === 401) {
                    $message = 'Invalid or expired personal token. Please check your ENVATO_PERSONAL_TOKEN.';
                } elseif ($statusCode === 403) {
                    // Check if the error mentions the specific scope required
                    if (isset($data['error']) && strpos($data['error'], 'sale:history') !== false) {
                        $message = 'Personal token does not have required permissions. The token needs the "View your items\' sales history" permission (scope: sale:history).';
                    } else {
                        $message = 'Personal token does not have required permissions. Ensure it has the "View your items\' sales history" permission (scope: sale:history).';
                    }
                } elseif ($statusCode === 404) {
                    $message = 'Purchase code not found or invalid.';
                } elseif ($errorDescription) {
                    $message = $errorDescription;
                } elseif ($errorMessage && $errorMessage !== 'Unknown error') {
                    $message = $errorMessage;
                }

                throw new PurchaseVerificationFailedException($message);
            }

            // The API returns sale data directly at root level, not nested under 'sale'
            if (! isset($data['item']) || ! isset($data['item']['id'])) {
                $this->logger->error('Purchase verification response missing item data', [
                    'response' => $data,
                    'purchase_code' => $purchaseCode,
                ]);

                throw new PurchaseVerificationFailedException('Invalid purchase code or response format');
            }

            return $data;
        } catch (PurchaseVerificationFailedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Purchase verification exception', [
                'exception' => $e->getMessage(),
                'purchase_code' => $purchaseCode,
            ]);

            throw new PurchaseVerificationFailedException('Failed to verify purchase code: '.$e->getMessage());
        }
    }

    /**
     * Activate license bound to machine ID
     *
     * @throws MachineIdRequiredException
     * @throws LicenseAlreadyActivatedException
     */
    private function activateMachineIdLicense(License $license, ?string $machineId): void
    {
        if (! $machineId) {
            throw new MachineIdRequiredException('Machine ID is required for this license type');
        }

        // Check if there's an active activation
        $activeActivation = Activation::where([
            'license_id' => $license->id,
            'is_active' => true,
        ])->first();

        if (! $activeActivation) {
            // First activation - create it
            $activation = new Activation;
            $activation->license_id = $license->id;
            $activation->machine_id = $machineId;
            $activation->is_active = true;
            $activation->activated_at = $this->clock->now()->format('Y-m-d H:i:s');
            $activation->created_at = $this->clock->now()->format('Y-m-d H:i:s');
            $activation->updated_at = $this->clock->now()->format('Y-m-d H:i:s');
            $activation->save();

            $this->logger->info('License activated: First activation', [
                'license_id' => $license->id,
                'machine_id' => $machineId,
            ]);

            return;
        }

        // Check if machine ID matches
        if ($activeActivation->machine_id === $machineId) {
            return;
        }

        // Different machine ID - reject
        $this->logger->warning('License verification failed: Machine ID mismatch', [
            'license_id' => $license->id,
            'expected_machine_id' => $activeActivation->machine_id,
            'provided_machine_id' => $machineId,
        ]);

        throw new LicenseAlreadyActivatedException('License is already activated on a different machine');
    }

    /**
     * Activate license bound to IP address
     *
     * @throws IpAddressRequiredException
     * @throws LicenseAlreadyActivatedException
     */
    private function activateIpAddressLicense(License $license, ?string $ipAddress): void
    {
        if (! $ipAddress) {
            throw new IpAddressRequiredException('IP address is required for this license type');
        }

        // Check if there's an active activation
        $activeActivation = Activation::where([
            'license_id' => $license->id,
            'is_active' => true,
        ])->first();

        if (! $activeActivation) {
            // First activation - create it
            $activation = new Activation;
            $activation->license_id = $license->id;
            $activation->ip_address = $ipAddress;
            $activation->is_active = true;
            $activation->activated_at = $this->clock->now()->format('Y-m-d H:i:s');
            $activation->created_at = $this->clock->now()->format('Y-m-d H:i:s');
            $activation->updated_at = $this->clock->now()->format('Y-m-d H:i:s');
            $activation->save();

            $this->logger->info('License activated: First activation', [
                'license_id' => $license->id,
                'ip_address' => $ipAddress,
            ]);

            return;
        }

        // Check if IP address matches
        if ($activeActivation->ip_address === $ipAddress) {
            return;
        }

        // Different IP address - reject
        $this->logger->warning('License verification failed: IP address mismatch', [
            'license_id' => $license->id,
            'expected_ip' => $activeActivation->ip_address,
            'provided_ip' => $ipAddress,
        ]);

        throw new LicenseAlreadyActivatedException('License is already activated on a different IP address');
    }

    /**
     * Reset a license (deactivate all activations)
     *
     * @param  string  $purchaseCode  Envato purchase code
     * @param  OAuthUser  $oauthUser  OAuth user performing the reset
     * @param  string|null  $reason  Reason for reset
     * @return true Returns true on successful reset
     *
     * @throws LicenseNotFoundException
     */
    public function reset(string $purchaseCode, OAuthUser $oauthUser, ?string $reason = null): bool
    {
        $license = License::where(['envato_purchase_code' => $purchaseCode])->first();

        if (! $license) {
            throw new LicenseNotFoundException('License not found');
        }

        // Verify purchase code belongs to the logged-in user
        if (! $oauthUser->access_token) {
            throw new \RuntimeException('OAuth access token not available');
        }

        $verificationResult = $this->verifyPurchaseOwnership(
            $oauthUser->access_token,
            $purchaseCode
        );

        if (! $verificationResult['success']) {
            throw new PurchaseVerificationFailedException(
                $verificationResult['error'] ?? 'Purchase code verification failed'
            );
        }

        $purchaseData = $verificationResult['purchase'] ?? [];
        $purchaseItemId = $purchaseData['item']['id'] ?? null;

        // Verify the purchase item ID matches the license item ID
        if ($purchaseItemId != $license->envato_item_id) {
            throw new PurchaseVerificationFailedException(
                'Purchase code does not belong to this item'
            );
        }

        // Deactivate all activations
        $activations = Activation::where([
            'license_id' => $license->id,
            'is_active' => true,
        ])->get();

        if ($activations) {
            foreach ($activations as $activation) {
                $activation->is_active = false;
                $activation->updated_at = $this->clock->now()->format('Y-m-d H:i:s');
                $activation->save();
            }
        }

        // Log the reset
        $reset = new LicenseReset;
        $reset->license_id = $license->id;
        $reset->oauth_user_id = $oauthUser->id;
        $reset->reset_reason = $reason;
        $reset->created_at = $this->clock->now()->format('Y-m-d H:i:s');
        $reset->updated_at = $this->clock->now()->format('Y-m-d H:i:s');
        $reset->save();

        $this->logger->info('License reset', [
            'license_id' => $license->id,
            'oauth_user_id' => $oauthUser->id,
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * Verify purchase code belongs to the OAuth user
     *
     * @param  string  $accessToken  OAuth access token
     * @param  string  $purchaseCode  Envato purchase code
     * @return array Purchase data or error
     */
    private function verifyPurchaseOwnership(string $accessToken, string $purchaseCode): array
    {
        try {
            $uri = self::ENVATO_BUYER_PURCHASE_URL.'?code='.urlencode($purchaseCode);
            $request = $this->requestFactory->createRequest('GET', $uri)
                ->withHeader('Authorization', 'Bearer '.$accessToken);

            $response = $this->httpClient->sendRequest($request);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger->warning('Purchase ownership verification failed', [
                    'status_code' => $statusCode,
                    'purchase_code' => $purchaseCode,
                ]);

                return [
                    'success' => false,
                    'error' => 'Purchase code verification failed',
                ];
            }

            $data = json_decode((string) $response->getBody(), true);

            return [
                'success' => true,
                'purchase' => $data,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception during purchase ownership verification', [
                'exception' => $e->getMessage(),
                'purchase_code' => $purchaseCode,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to verify purchase ownership: '.$e->getMessage(),
            ];
        }
    }
}

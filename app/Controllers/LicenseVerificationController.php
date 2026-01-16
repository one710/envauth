<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\InvalidItemIdException;
use App\Exceptions\IpAddressRequiredException;
use App\Exceptions\LicenseAlreadyActivatedException;
use App\Exceptions\LicenseInactiveException;
use App\Exceptions\MachineIdRequiredException;
use App\Exceptions\PurchaseVerificationFailedException;
use App\Services\LicenseVerificationService;
use Phast\Controller;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LicenseVerificationController extends Controller
{
    public function __construct(
        private readonly LicenseVerificationService $licenseService
    ) {}

    /**
     * Verify license endpoint
     * POST /api/license/verify
     */
    public function verify(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];

        // Validate input
        $validation = $this->validate($body, function ($validator) {
            $validator->required('purchase_code')->isNotBlank()->isString();
            $validator->required('item_id')->isNotBlank();
            $validator->key('machine_id')->isString();
        });

        if (! $validation->valid()) {
            $errors = $validation->errors();

            return $this->json([
                'success' => false,
                'message' => array_values($errors)[0] ?? 'Validation failed',
                'errors' => $errors,
            ], 400);
        }

        $purchaseCode = $body['purchase_code'];
        $itemId = $body['item_id'];
        $machineId = $body['machine_id'] ?? null;

        // Get client IP from middleware (handles proxies securely)
        // The ClientIp middleware sets this attribute after processing trusted proxies
        $ipAddress = $request->getAttribute('client-ip');

        try {
            $this->licenseService->verify($purchaseCode, $itemId, $machineId, $ipAddress);

            return $this->json([
                'success' => true,
                'message' => 'License verified and activated successfully',
            ], 200);
        } catch (InvalidItemIdException|PurchaseVerificationFailedException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (LicenseInactiveException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (MachineIdRequiredException|IpAddressRequiredException|LicenseAlreadyActivatedException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}

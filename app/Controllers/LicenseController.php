<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\LicenseNotFoundException;
use App\Exceptions\PurchaseVerificationFailedException;
use App\Models\OAuthUser;
use App\Services\LicenseVerificationService;
use Phast\Controller;
use Phlash\FlashInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LicenseController extends Controller
{
    public function __construct(
        private readonly LicenseVerificationService $licenseService,
        private readonly FlashInterface $flash
    ) {}

    /**
     * Show license reset page (requires OAuth authentication)
     * GET /license/reset
     */
    public function showResetPage(ServerRequestInterface $request): ResponseInterface
    {
        // Check if user is authenticated via OAuth
        $oauthUserId = $_SESSION['oauth_user_id'] ?? null;

        if (! $oauthUserId) {
            // Redirect to OAuth login
            return $this->redirect('/oauth/login?return_url='.urlencode('/license/reset'));
        }

        // Fetch user from database to get the Envato username
        $oauthUser = OAuthUser::find((int) $oauthUserId);

        // If user doesn't exist in database, clear session and redirect to login
        if (! $oauthUser) {
            unset($_SESSION['oauth_user_id']);
            unset($_SESSION['oauth_username']);

            return $this->redirect('/oauth/login?return_url='.urlencode('/license/reset'));
        }

        $username = $oauthUser->username;

        return $this->render('license/reset', [
            'user' => [
                'username' => $username,
            ],
            'show_form' => true,
        ]);
    }

    /**
     * Handle license reset form submission (requires OAuth authentication)
     * POST /license/reset
     */
    public function handleReset(ServerRequestInterface $request): ResponseInterface
    {
        // Check if user is authenticated via OAuth
        $oauthUserId = $_SESSION['oauth_user_id'] ?? null;

        if (! $oauthUserId) {
            // Redirect to OAuth login
            return $this->redirect('/oauth/login?return_url='.urlencode('/license/reset'));
        }

        // Fetch user from database to get the Envato username
        $oauthUser = OAuthUser::find((int) $oauthUserId);

        // If user doesn't exist in database, clear session and redirect to login
        if (! $oauthUser) {
            unset($_SESSION['oauth_user_id']);
            unset($_SESSION['oauth_username']);

            return $this->redirect('/oauth/login?return_url='.urlencode('/license/reset'));
        }

        $username = $oauthUser->username;

        // Get form data
        $body = $request->getParsedBody() ?? [];

        // Validate input
        $validation = $this->validate($body, function ($validator) {
            $validator->required('purchase_code')->isNotBlank()->isString();
            $validator->key('reason')->isString();
        });

        if (! $validation->valid()) {
            $errors = $validation->errors();
            $this->flash->flashNow('error', array_values($errors)[0] ?? 'Validation failed');

            return $this->render('license/reset', [
                'user' => [
                    'username' => $username,
                ],
                'purchase_code' => $body['purchase_code'] ?? '',
                'reason' => $body['reason'] ?? '',
            ]);
        }

        $purchaseCode = $body['purchase_code'];
        $reason = $body['reason'] ?? null;

        try {
            $this->licenseService->reset($purchaseCode, $oauthUser, $reason);

            $this->flash->flashNow('success', 'License reset successfully. You can now activate it on a new machine/IP address.');

            return $this->render('license/reset', [
                'user' => [
                    'username' => $username,
                ],
                'show_form' => false,
            ]);
        } catch (LicenseNotFoundException $e) {
            $this->flash->flashNow('error', $e->getMessage());

            return $this->render('license/reset', [
                'user' => [
                    'username' => $username,
                ],
                'purchase_code' => $purchaseCode,
                'reason' => $reason,
            ]);
        } catch (PurchaseVerificationFailedException $e) {
            $this->flash->flashNow('error', 'Unable to verify purchase code. Please ensure the purchase code belongs to your Envato account and try again.');

            return $this->render('license/reset', [
                'user' => [
                    'username' => $username,
                ],
                'purchase_code' => $purchaseCode,
                'reason' => $reason,
            ]);
        }
    }
}

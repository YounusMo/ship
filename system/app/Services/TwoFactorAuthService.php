<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * Thin wrapper around the google2fa library. Generates secrets, builds
 * the otpauth URI, renders a QR code as inline SVG, and verifies codes.
 *
 * @see docs/GAPS.md gap #6
 */
class TwoFactorAuthService
{
    private const ISSUER = 'ShipFlow';

    public function __construct(private Google2FA $google2fa = new Google2FA()) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Build the otpauth:// URI that any TOTP app understands.
     */
    public function otpauthUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(self::ISSUER, $user->email, $secret);
    }

    /**
     * Render the QR code as an inline SVG string.
     */
    public function qrCodeSvg(User $user, string $secret, int $size = 240): string
    {
        $uri = $this->otpauthUri($user, $secret);
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd(),
        );
        return (new Writer($renderer))->writeString($uri);
    }

    /**
     * Verify a 6-digit code against the user's secret. Accepts the
     * default ±1 window so a code on the boundary of two periods still
     * verifies.
     */
    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        return $this->google2fa->verifyKey($secret, $code);
    }

    public function isEnrolled(User $user): bool
    {
        return $user->two_factor_secret !== null
            && $user->two_factor_confirmed_at !== null;
    }
}

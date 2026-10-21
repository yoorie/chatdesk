<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Branding Service
 *
 * Manages platform branding: name, logo, favicon, colors, etc.
 */
class BrandingService
{
    protected PlatformSettingsService $settingsService;

    public function __construct(PlatformSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Get platform name.
     */
    public function getPlatformName(): string
    {
        return $this->settingsService->get('branding.platform_name', config('app.name', 'ChatDesk'));
    }

    /**
     * Get platform logo URL.
     */
    public function getLogoUrl(): ?string
    {
        return $this->urlForPath($this->settingsService->get('branding.logo_path'));
    }

    public function getDarkLogoUrl(): ?string
    {
        return $this->urlForPath($this->settingsService->get('branding.logo_dark_path'));
    }

    public function getSidebarIconUrl(): ?string
    {
        return $this->urlForPath($this->settingsService->get('branding.sidebar_icon_path'));
    }

    public function getDarkSidebarIconUrl(): ?string
    {
        return $this->urlForPath($this->settingsService->get('branding.sidebar_icon_dark_path'));
    }

    /**
     * Get favicon URL.
     */
    public function getFaviconUrl(): ?string
    {
        return $this->urlForPath($this->settingsService->get('branding.favicon_path'));
    }

    public function getDarkFaviconUrl(): ?string
    {
        return $this->urlForPath($this->settingsService->get('branding.favicon_dark_path'));
    }

    /**
     * Get primary color.
     */
    public function getPrimaryColor(): string
    {
        return $this->settingsService->get('branding.primary_color', '#00A548');
    }

    /**
     * Get secondary color.
     */
    public function getSecondaryColor(): string
    {
        return $this->settingsService->get('branding.secondary_color', '#053A35');
    }

    public function colorRgb(string $color, string $fallback): string
    {
        $hex = preg_match('/^#[a-f0-9]{6}$/i', $color) ? $color : $fallback;

        return implode(' ', array_map(fn ($offset) => hexdec(substr($hex, $offset, 2)), [1, 3, 5]));
    }

    /**
     * Get all branding settings.
     */
    public function getAll(): array
    {
        $showPoweredBy = $this->settingsService->get('branding.show_powered_by', false);
        // Ensure boolean type
        if (is_string($showPoweredBy)) {
            $showPoweredBy = $showPoweredBy === '1' || $showPoweredBy === 'true';
        }

        return [
            'platform_name' => $this->getPlatformName(),
            'logo_url' => $this->getLogoUrl(),
            'logo_dark_url' => $this->getDarkLogoUrl(),
            'sidebar_icon_url' => $this->getSidebarIconUrl(),
            'sidebar_icon_dark_url' => $this->getDarkSidebarIconUrl(),
            'favicon_url' => $this->getFaviconUrl(),
            'favicon_dark_url' => $this->getDarkFaviconUrl(),
            'primary_color' => $this->getPrimaryColor(),
            'secondary_color' => $this->getSecondaryColor(),
            'support_email' => $this->settingsService->get('branding.support_email'),
            'support_phone' => $this->settingsService->get('branding.support_phone'),
            'footer_text' => $this->settingsService->get('branding.footer_text'),
            'show_powered_by' => (bool) $showPoweredBy];
    }

    /**
     * Upload logo.
     */
    public function uploadLogo(\Illuminate\Http\UploadedFile $file): string
    {
        return $this->uploadAsset($file, 'branding.logo_path');
    }

    public function uploadDarkLogo(\Illuminate\Http\UploadedFile $file): string
    {
        return $this->uploadAsset($file, 'branding.logo_dark_path');
    }

    public function uploadSidebarIcon(\Illuminate\Http\UploadedFile $file): string
    {
        return $this->uploadAsset($file, 'branding.sidebar_icon_path');
    }

    public function uploadDarkSidebarIcon(\Illuminate\Http\UploadedFile $file): string
    {
        return $this->uploadAsset($file, 'branding.sidebar_icon_dark_path');
    }

    /**
     * Upload favicon.
     */
    public function uploadFavicon(\Illuminate\Http\UploadedFile $file): string
    {
        return $this->uploadAsset($file, 'branding.favicon_path');
    }

    public function uploadDarkFavicon(\Illuminate\Http\UploadedFile $file): string
    {
        return $this->uploadAsset($file, 'branding.favicon_dark_path');
    }

    public function deleteLogo(): void
    {
        $this->deleteAsset('branding.logo_path');
    }

    public function deleteDarkLogo(): void
    {
        $this->deleteAsset('branding.logo_dark_path');
    }

    public function deleteSidebarIcon(): void
    {
        $this->deleteAsset('branding.sidebar_icon_path');
    }

    public function deleteDarkSidebarIcon(): void
    {
        $this->deleteAsset('branding.sidebar_icon_dark_path');
    }

    public function deleteFavicon(): void
    {
        $this->deleteAsset('branding.favicon_path');
    }

    public function deleteDarkFavicon(): void
    {
        $this->deleteAsset('branding.favicon_dark_path');
    }

    protected function uploadAsset(\Illuminate\Http\UploadedFile $file, string $settingKey): string
    {
        $this->deleteAsset($settingKey);

        $path = $file->store('branding', 'public');
        PlatformSetting::set($settingKey, $path, 'string', 'branding');

        return $path;
    }

    protected function deleteAsset(string $settingKey): void
    {
        $path = $this->settingsService->get($settingKey);
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        PlatformSetting::set($settingKey, '', 'string', 'branding');
    }

    protected function urlForPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::url($path);
    }
}

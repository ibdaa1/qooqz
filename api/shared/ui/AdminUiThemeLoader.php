<?php
declare(strict_types=1);

// api/shared/ui/AdminUiThemeLoader.php

final class AdminUiThemeLoader
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get full theme data for admin UI
     */
    public function getFullThemeData(int $tenantId, ?int $themeId = null): array
    {
        $themeId = $themeId ?? $this->getActiveThemeId($tenantId);
        error_log('AdminUiThemeLoader: Tenant ID: ' . $tenantId . ', Theme ID: ' . $themeId);

        if (!$themeId) {
            error_log('AdminUiThemeLoader: No active theme found');
            return [];
        }

        $data = [
            'theme'           => $this->getTheme($tenantId, $themeId),
            'design_settings' => $this->getDesignSettings($tenantId, $themeId),
            'color_settings'  => $this->getColorSettings($tenantId, $themeId),
            'font_settings'   => $this->getFontSettings($tenantId, $themeId),
            'button_styles'   => $this->getButtonStyles($tenantId, $themeId),
            'card_styles'     => $this->getCardStyles($tenantId, $themeId),
            'system_settings' => $this->getSystemSettings($tenantId),
            'tenant'          => $this->getTenant($tenantId),
            'tenant_users'    => $this->getTenantUsers($tenantId),
        ];

        // Generate CSS
        $data['generated_css'] = $this->generateCss($data);
        error_log('AdminUiThemeLoader: Theme data loaded successfully');
        return $data;
    }

    public function getActiveThemeId(int $tenantId): ?int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM themes
                WHERE tenant_id = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                error_log('AdminUiThemeLoader: Found active theme ID: ' . $row['id']);
                return (int)$row['id'];
            }

            // Fallback to default
            $stmt = $this->pdo->prepare("
                SELECT id FROM themes
                WHERE tenant_id = ? AND is_default = 1
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                error_log('AdminUiThemeLoader: Found default theme ID: ' . $row['id']);
                return (int)$row['id'];
            }

            error_log('AdminUiThemeLoader: No theme found');
            return null;
        } catch (Throwable $e) {
            error_log('AdminUiThemeLoader: Error in getActiveThemeId: ' . $e->getMessage());
            return null;
        }
    }

    public function getTheme(int $tenantId, int $themeId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM themes
                WHERE tenant_id = ? AND id = ?
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $themeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log('AdminUiThemeLoader: getTheme row: ' . json_encode($row));
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('AdminUiThemeLoader: Error in getTheme: ' . $e->getMessage());
            return null;
        }
    }

    public function getDesignSettings(int $tenantId, int $themeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM design_settings
                WHERE tenant_id = ? AND theme_id = ? AND is_active = 1
                ORDER BY category, sort_order
            ");
            $stmt->execute([$tenantId, $themeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('AdminUiThemeLoader: Error in getDesignSettings: ' . $e->getMessage());
            return [];
        }
    }

    public function getColorSettings(int $tenantId, int $themeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM color_settings
                WHERE tenant_id = ? AND theme_id = ? AND is_active = 1
                ORDER BY category, sort_order
            ");
            $stmt->execute([$tenantId, $themeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('AdminUiThemeLoader: Error in getColorSettings: ' . $e->getMessage());
            return [];
        }
    }

    public function getFontSettings(int $tenantId, int $themeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM font_settings
                WHERE tenant_id = ? AND theme_id = ? AND is_active = 1
                ORDER BY category, sort_order
            ");
            $stmt->execute([$tenantId, $themeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('AdminUiThemeLoader: Error in getFontSettings: ' . $e->getMessage());
            return [];
        }
    }

    public function getButtonStyles(int $tenantId, int $themeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM button_styles
                WHERE tenant_id = ? AND theme_id = ? AND is_active = 1
                ORDER BY button_type, name
            ");
            $stmt->execute([$tenantId, $themeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('AdminUiThemeLoader: Error in getButtonStyles: ' . $e->getMessage());
            return [];
        }
    }

    public function getCardStyles(int $tenantId, int $themeId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM card_styles
                WHERE tenant_id = ? AND (theme_id = ? OR theme_id IS NULL) AND is_active = 1
                ORDER BY card_type, name
            ");
            $stmt->execute([$tenantId, $themeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('AdminUiThemeLoader: Error in getCardStyles: ' . $e->getMessage());
            return [];
        }
    }

    public function getSystemSettings(int $tenantId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM system_settings
                WHERE tenant_id = ? AND is_public = 1
                ORDER BY category, setting_key
            ");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('AdminUiThemeLoader: Error in getSystemSettings: ' . $e->getMessage());
            return [];
        }
    }

    public function getTenant(int $tenantId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.*, u.username AS owner_username
                FROM tenants t
                LEFT JOIN tenant_users tu ON t.id = tu.tenant_id AND tu.user_id = t.owner_user_id
                LEFT JOIN users u ON tu.user_id = u.id
                WHERE t.id = ?
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('AdminUiThemeLoader: Error in getTenant: ' . $e->getMessage());
            return null;
        }
    }

    public function getTenantUsers(int $tenantId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT tu.*, u.username, r.name AS role_name
                FROM tenant_users tu
                LEFT JOIN users u ON tu.user_id = u.id
                LEFT JOIN roles r ON tu.role_id = r.id
                WHERE tu.tenant_id = ?
                ORDER BY tu.joined_at DESC
            ");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('AdminUiThemeLoader: Error in getTenantUsers: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate CSS from theme data.
     * CSS variable names are hyphenated (--primary-color) so they match
     * the CSS framework which always uses hyphen-format var() references.
     */
    public function generateCss(array $themeData): string
    {
        // Helper: convert snake_case key to hyphenated CSS var name
        $hyphenateKey = static fn(string $key): string => str_replace('_', '-', strtolower($key));

        $css = ":root {\n";

        // Colors — emit both underscore AND hyphenated form for maximum compatibility
        foreach ($themeData['color_settings'] ?? [] as $color) {
            if (empty($color['setting_key']) || empty($color['color_value'])) continue;
            $hyphen = $hyphenateKey($color['setting_key']);
            $css .= "  --{$hyphen}: {$color['color_value']};\n";
            // Also emit the original underscore form in case anything references it
            if ($color['setting_key'] !== $hyphen) {
                $css .= "  --{$color['setting_key']}: {$color['color_value']};\n";
            }
        }

        // Fonts — emit hyphenated var names
        foreach ($themeData['font_settings'] ?? [] as $font) {
            if (empty($font['setting_key'])) continue;
            $hyphen = $hyphenateKey($font['setting_key']);
            if (!empty($font['font_family'])) {
                $css .= "  --{$hyphen}-family: {$font['font_family']};\n";
            }
            if (!empty($font['font_size'])) {
                $css .= "  --{$hyphen}-size: {$font['font_size']};\n";
            }
            if (!empty($font['font_weight'])) {
                $css .= "  --{$hyphen}-weight: {$font['font_weight']};\n";
            }
        }

        $css .= "}\n";

        // Buttons
        foreach ($themeData['button_styles'] ?? [] as $button) {
            if (empty($button['slug'])) continue;
            $css .= ".btn-{$button['slug']} {\n";
            if (!empty($button['background_color'])) $css .= "  background-color: {$button['background_color']};\n";
            if (!empty($button['text_color'])) $css .= "  color: {$button['text_color']};\n";
            if (!empty($button['border_color'])) $css .= "  border: " . ($button['border_width'] ?? 1) . "px solid {$button['border_color']};\n";
            if (!empty($button['border_radius'])) $css .= "  border-radius: {$button['border_radius']}px;\n";
            if (!empty($button['padding'])) $css .= "  padding: {$button['padding']};\n";
            if (!empty($button['font_size'])) $css .= "  font-size: {$button['font_size']};\n";
            if (!empty($button['font_weight'])) $css .= "  font-weight: {$button['font_weight']};\n";
            $css .= "}\n";
        }

        // Cards
        foreach ($themeData['card_styles'] ?? [] as $card) {
            if (empty($card['slug'])) continue;
            $css .= ".card-{$card['slug']} {\n";
            if (!empty($card['background_color'])) $css .= "  background-color: {$card['background_color']};\n";
            if (!empty($card['border_color'])) $css .= "  border: " . ($card['border_width'] ?? 1) . "px solid {$card['border_color']};\n";
            if (!empty($card['border_radius'])) $css .= "  border-radius: {$card['border_radius']}px;\n";
            if (!empty($card['shadow_style'])) $css .= "  box-shadow: {$card['shadow_style']};\n";
            if (!empty($card['padding'])) $css .= "  padding: {$card['padding']};\n";
            $css .= "}\n";
        }

        return $css;
    }
}
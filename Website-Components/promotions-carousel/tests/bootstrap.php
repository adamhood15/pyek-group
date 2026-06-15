<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap — loads the helper class and stubs all WordPress functions
 * that the render methods depend on, so tests run without a WordPress install.
 *
 * Escape stubs deliberately match WordPress semantics:
 *   esc_html / esc_attr  → htmlspecialchars with ENT_QUOTES | ENT_SUBSTITUTE
 *   esc_url              → strips javascript:/data:/vbscript: then htmlspecialchars
 *
 * These stubs are wrapped in function_exists() so the bootstrap is safe to load
 * inside a real WordPress environment (e.g. wp-env or Lando) without conflicts.
 */

require_once __DIR__ . '/../PromotionsCarouselHelper.php';

// ── WordPress escape stubs ────────────────────────────────────────────────────

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    /**
     * Minimal esc_url stub. Real WP does more (IDN encoding, etc.) but for
     * test purposes we need: strip dangerous protocols, HTML-encode the result.
     */
    function esc_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        // Strip schemes that execute code in href/src attributes.
        if (preg_match('/^(?:javascript|data|vbscript)\s*:/i', $url)) {
            return '';
        }
        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

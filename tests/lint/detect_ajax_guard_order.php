<?php
/** WHAT: public AJAX nonce rejection must run before object resolution, metadata reads, remote dispatch, or writes. Production: five nopriv render/image methods. */
require_once __DIR__ . '/source-contract-lib.php';

$wpsr = wpsr_source_contract_start('ajax-guard-order', $argv);
try {
    $methods = [
        ['app/Hooks/Handlers/ShortcodeHandler.php', 'handleLoadMoreAjax', '/check_ajax_referer\s*\(\s*[\'\"]wpsr-ajax-nonce[\'\"]/', '/isPublicRenderableTemplate\s*\(/'],
        ['app/Hooks/Handlers/YoutubeTemplateHandler.php', 'renderPopupFeed', '/check_ajax_referer\s*\(\s*[\'\"]wpsr-ajax-nonce[\'\"]/', '/isPublicRenderableTemplate\s*\(/'],
        ['app/Hooks/Handlers/YoutubeTemplateHandler.php', 'renderFluentPlayer', '/check_ajax_referer\s*\(\s*[\'\"]wpsr-ajax-nonce[\'\"]/', '/isPublicRenderableTemplate\s*\(/'],
        ['app/Services/Platforms/ImageOptimizationHandler.php', 'savePhotos', '/\$nonce_verified\s*=/', '/isPublicRenderableTemplate\s*\(/'],
        ['app/Services/Platforms/ReviewImageOptimizationHandler.php', 'savePhotos', '/\$nonce_verified\s*=/', '/isPublicRenderableTemplate\s*\(/'],
    ];
    foreach ($methods as $contract) {
        list($file, $method, $nonce, $boundary) = $contract;
        $slice = $wpsr->phpMethod($file, $method);
        $wpsr->requireBefore($slice, $nonce, $boundary, $method . ' nonce verification must precede public object authorization');
    }

    foreach (['app/Services/Platforms/ImageOptimizationHandler.php', 'app/Services/Platforms/ReviewImageOptimizationHandler.php'] as $file) {
        $slice = $wpsr->phpMethod($file, 'savePhotos');
        $wpsr->requirePattern($slice, '/wp_verify_nonce\s*\(\s*\$nonce\s*,\s*[\'\"]wpsr-ajax-nonce[\'\"]\s*\)\s*\|\|\s*wp_verify_nonce\s*\(\s*\$nonce\s*,\s*[\'\"]wp-social-reviews[\'\"]\s*\)/', 'image handler must accept only explicit frontend/admin nonce actions');
    }
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();

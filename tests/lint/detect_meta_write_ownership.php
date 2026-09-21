<?php
/** WHAT: template/chat editor IDs must resolve to plugin-owned post types before any metadata/content write. Production: Reviews MetaController::update, Chats MetaController::update, SocialChat::updateSettings. */
require_once __DIR__ . '/source-contract-lib.php';

$wpsr = wpsr_source_contract_start('meta-write-ownership', $argv);
try {
    $reviewGuard = $wpsr->phpMethod('app/Http/Controllers/Platforms/Reviews/MetaController.php', 'getTemplatePost');
    $wpsr->requirePattern($reviewGuard, '/in_array\s*\(\s*\$templateDetails->post_type\s*,\s*self::ALLOWED_POST_TYPES\s*,\s*true\s*\)/', 'review editor ownership helper must strictly allowlist post types');

    $reviewUpdate = $wpsr->phpMethod('app/Http/Controllers/Platforms/Reviews/MetaController.php', 'update');
    foreach (['/do_action\s*\(\s*[\'\"]wpsocialreviews\/template_meta_data[\'\"]/', '/update_post_meta\s*\(/', '/wp_update_post\s*\(/'] as $sink) {
        $wpsr->requireBefore($reviewUpdate, '/getTemplatePost\s*\(/', $sink, 'review template ownership rejection must precede every write sink');
    }
    $wpsr->requirePattern($reviewUpdate, '/table\s*\(\s*[\'\"]posts[\'\"]\s*\).*?where\s*\(\s*[\'\"]ID[\'\"]\s*,\s*\$templateId\s*\).*?whereIn\s*\(\s*[\'\"]post_type[\'\"]\s*,\s*self::ALLOWED_POST_TYPES\s*\).*?update\s*\(/s', 'raw review menu-order update must reassert allowed post types');

    $chatGuard = $wpsr->phpMethod('app/Http/Controllers/Platforms/Chats/MetaController.php', 'isChatWidget');
    $wpsr->requirePattern($chatGuard, '/get_post_type\s*\(\s*\$postId\s*\)\s*===\s*self::CHAT_POST_TYPE/', 'chat ownership helper must require exact chat post type');
    $chatUpdate = $wpsr->phpMethod('app/Http/Controllers/Platforms/Chats/MetaController.php', 'update');
    $wpsr->requireBefore($chatUpdate, '/isChatWidget\s*\(/', '/do_action\s*\(\s*[\'\"]wpsocialreviews\/update_chat_settings[\'\"]/', 'chat object guard must precede update hook dispatch');

    $chatService = $wpsr->phpMethod('app/Services/Platforms/Chats/SocialChat.php', 'updateSettings');
    $wpsr->requirePattern($chatService, '/table\s*\(\s*[\'\"]posts[\'\"]\s*\).*?where\s*\(\s*[\'\"]ID[\'\"]\s*,\s*\$postId\s*\).*?where\s*\(\s*[\'\"]post_type[\'\"]\s*,\s*[\'\"]wpsr_social_chats[\'\"]\s*\).*?update\s*\(/s', 'raw chat menu-order update must reassert chat post type');
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();

<?php
/** WHAT: public shortcodes/AJAX may dispatch only publicly renderable, non-password templates. Production: ShortcodeHandler and YoutubeTemplateHandler render entry methods. */
require_once __DIR__ . '/source-contract-lib.php';

$wpsr = wpsr_source_contract_start('public-template-visibility', $argv);
try {
    $policy = $wpsr->phpMethod('app/Hooks/Handlers/ShortcodeHandler.php', 'isPublicRenderableTemplate');
    $wpsr->requirePattern($policy, '/\babsint\s*\(\s*\$wpsr_template_id\s*\)/', 'template ID must be normalized before lookup');
    $wpsr->requirePattern($policy, '/\bget_post\s*\(\s*\$wpsr_template_id\s*\)/', 'visibility policy must resolve real post object');
    $wpsr->requirePattern($policy, '/\bin_array\s*\(\s*\$wpsr_template_post->post_type\s*,\s*\$wpsr_allowed_post_types\s*,\s*true\s*\)/', 'post type ownership must use strict allowlist');
    $wpsr->requirePattern($policy, '/post_status\s*===\s*[\'\"]publish[\'\"]\s*&&\s*!\s*post_password_required\s*\(\s*\$wpsr_template_post\s*\)/', 'published templates must still pass password challenge');
    $wpsr->requirePattern($policy, '/current_user_can\s*\(\s*[\'\"]edit_post[\'\"]\s*,\s*\$wpsr_template_id\s*\)/', 'non-public admin preview fallback must remain object-capability scoped');

    $shortcode = $wpsr->phpMethod('app/Hooks/Handlers/ShortcodeHandler.php', 'makeShortcode');
    $wpsr->requireBefore($shortcode, '/isPublicRenderableTemplate\s*\(/', '/enqueueStyles\s*\(/', 'shortcode visibility check must precede renderer assets/dispatch');

    $loadMore = $wpsr->phpMethod('app/Hooks/Handlers/ShortcodeHandler.php', 'handleLoadMoreAjax');
    $wpsr->requireBefore($loadMore, '/isPublicRenderableTemplate\s*\(/', '/new\s+YoutubeTemplateHandler\s*\(\s*\)\s*\)\s*->getPaginatedFeedHtml\s*\(/', 'load-more visibility check must precede first feed dispatch');

    foreach (['renderPopupFeed', 'renderFluentPlayer'] as $method) {
        $slice = $wpsr->phpMethod('app/Hooks/Handlers/YoutubeTemplateHandler.php', $method);
        $wpsr->requireBefore($slice, '/isPublicRenderableTemplate\s*\(/', '/templateMeta\s*\(/', $method . ' visibility check must precede template metadata read');
    }
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();

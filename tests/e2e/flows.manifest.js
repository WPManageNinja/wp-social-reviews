'use strict';

/**
 * Core-free E2E flow inventory.
 *
 * Phase 17 supplied three substitute flows because core has no money lifecycle.
 * Phase 21 adds three remaining customer-critical local flows. Keep phase ownership
 * explicit so drift output cannot make Phase 17 and Phase 21 look like one claim.
 */
module.exports = [
    {
        id: 'review-moderation',
        phase: 17,
        title: 'pending review approval and spam moderation',
        route: '/reviews',
        sources: [
            {
                file: 'resources/admin/components/views/AllReviews.vue',
                snippets: [
                    'wpsr-templates-table',
                    'wpsr-tab-button',
                    'wpsr-controls-bulk-actions',
                    'command="approve"',
                    'command="mark-spam"',
                    'command="not-spam"',
                ],
            },
            {
                file: 'resources/admin/mixins/tableMixin.js',
                snippets: [
                    "this.$put(`${this.endpoint}/status-update`",
                    "this.$put(`${this.endpoint}/spam`",
                ],
            },
        ],
    },
    {
        id: 'review-template-lifecycle',
        phase: 17,
        title: 'review template create, configure, preview, list, cancel-delete, and delete',
        route: '/templates',
        sources: [
            {
                file: 'resources/admin/components/views/AllTemplates.vue',
                snippets: [
                    'wpsr-all-templates-page',
                    "{{ $t('Create Template') }}",
                    'wpsr-shortcode',
                    'wpsr-actions',
                    '@click="confirmDelete()"',
                ],
            },
            {
                file: 'resources/admin/components/reviews/editor/ReviewsView.vue',
                snippets: ['wpsr_editor', '@update-settings="saveTemplateMeta"'],
            },
            {
                file: 'resources/admin/components/reviews/editor/editor-ui/ReviewsEditor.vue',
                snippets: ['wpsr-editor-minus-btn', "{{ $t('Filters') }}"],
            },
            {
                file: 'resources/admin/components/pieces/EditorHeader.vue',
                snippets: ['settings_header', 'wpsr_light_edit', 'wpsr_secondary_btn'],
            },
        ],
    },
    {
        id: 'chat-widget-lifecycle',
        phase: 17,
        title: 'chat widget create, channel configure, publish render, disable, re-enable, and delete',
        route: '/chat-widgets',
        sources: [
            {
                file: 'resources/admin/components/views/ChatWidgets.vue',
                snippets: [
                    'wpsr-all-templates-page',
                    "{{ $t('Create Chat Widget') }}",
                    '<el-option label="Enable" value="publish">',
                    '<el-option label="Disable" value="draft">',
                    'wpsr-actions',
                ],
            },
            {
                file: 'resources/admin/components/chats/editor/ChatView.vue',
                snippets: ['wpsr_editor', "this.$put(`chat-widgets/meta/chats/${widget_id}`"],
            },
            {
                file: 'resources/admin/components/chats/editor/editor-ui/ChatEditor.vue',
                snippets: ['iconKey="priority"', "title: this.$t('Priority')"],
            },
            {
                file: 'resources/admin/components/chats/editor/editor-ui/ChannelTab.vue',
                snippets: [
                    'wpsr-social-channel-credential-input',
                    'wpsr_primary_light_btn',
                    'this.chat_config.channels.push(this.selected_channel)',
                ],
            },
            {
                file: 'resources/admin/components/views/platforms/chats/AllChatChannels.vue',
                snippets: ['wpsr-social-channel-card'],
            },
            {
                file: 'app/Views/public/chat-templates/template1.php',
                snippets: ['id="wpsr-chat-widget-', 'wpsr-fm-chat-wrapper'],
            },
        ],
    },
    {
        id: 'review-category-lifecycle',
        phase: 21,
        title: 'assign new category, cancel rename/delete, rename, and delete through reviews UI',
        route: '/reviews',
        sources: [
            {
                file: 'resources/admin/components/views/AllReviews.vue',
                snippets: [
                    "{{ $t('Assign Category') }}",
                    "{{ $t('Manage Categories') }}",
                    '<BulkCategoryModal',
                    '<ManageCategoriesModal',
                    '@close="removeCategoryFromReview(scope.row, category)"',
                ],
            },
            {
                file: 'resources/admin/components/pieces/BulkCategoryModal.vue',
                snippets: [
                    'wpsr-bulk-category-modal',
                    "{ action: action, new_category_name: this.newName.trim() }",
                ],
            },
            {
                file: 'resources/admin/components/pieces/ManageCategoriesModal.vue',
                snippets: [
                    'wpsr-manage-categories-modal',
                    'this.$put(`${this.routePrefix}/categories/${category.id}`',
                    'this.$del(`${this.routePrefix}/categories/${category.id}`',
                ],
            },
            {
                file: 'resources/admin/mixins/reviewCategoryMixin.js',
                snippets: [
                    'this.$put(`${this.categoryRoutePrefix}/bulk-category`',
                    'onCategoriesChanged()',
                ],
            },
        ],
    },
    {
        id: 'review-filtering-search',
        phase: 21,
        title: 'review search, empty-result boundary, platform filter, clear, and persisted no-op',
        route: '/reviews',
        sources: [
            {
                file: 'resources/admin/components/views/AllReviews.vue',
                snippets: [
                    'placeholder="Search..."',
                    '@keyup.enter="getItems()"',
                    '@clear="handleClear"',
                    ':placeholder="$t(\'Select Platform\')"',
                ],
            },
            {
                file: 'resources/admin/mixins/tableMixin.js',
                snippets: [
                    'search: this.search_string',
                    'filter: this.filter_value',
                    "this.search_string = '';",
                ],
            },
            {
                file: 'app/Http/Controllers/Platforms/Reviews/RecommendationsController.php',
                snippets: [
                    "$search  = sanitize_text_field",
                    "$reviews = Review::searchBy($search);",
                    "$reviews = $reviews->where('platform_name', $filter);",
                ],
            },
        ],
    },
    {
        id: 'translation-settings-persistence',
        phase: 21,
        title: 'translation save, sanitized persisted reload, and unsaved-edit no-op',
        route: '/settings/translation-management',
        sources: [
            {
                file: 'resources/admin/components/views/settings/others/Translation.vue',
                snippets: [
                    "{{$t('Translation Settings')}}",
                    '@click="saveSettings"',
                    'v-model="translations[field.fieldKey]"',
                    "this.$post('settings/translations'",
                ],
            },
            {
                file: 'app/Http/Controllers/SettingsController.php',
                snippets: [
                    "'read_more'  => 'sanitize_text_field'",
                    "$settings['global_settings']['translations'] = $translationsSettings;",
                    "update_option('wpsr_global_settings', $globalSettings);",
                ],
            },
        ],
    },
];

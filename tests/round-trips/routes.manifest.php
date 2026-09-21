<?php
/**
 * Phase 9 disposition for every core mutating route declaration.
 *
 * `round-trip`: participates in create/read/update-one-field/full-snapshot/delete.
 * `reversible`: local write with before/change/after full-snapshot invariants.
 * `covered-prior`: narrow state/action already value-tested in Phases 5–8.
 * `alias`: second mounted URL for same global handler; canonical path is tested.
 * `deferred`: non-round-trip action, external seam, destructive reset, or later phase.
 */
return [
    'POST platforms:/update-statuses' => ['family' => 'platform-status-settings', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Fixed wpsr_statuses option is pre-existing lab/plugin state; mutating unowned installed_time container violates Phase 9 option ownership.'],
    'POST platforms:/subscribe' => ['family' => 'newsletter', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Outbound newsletter subscription; no local CRUD resource.'],
    'POST platforms:/addons' => ['family' => 'plugin-install', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Plugin download/install/activation action; external and globally destructive.'],

    'POST platforms/reviews:/configs' => ['family' => 'review-platform-config', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Credential/OAuth verification invokes remote provider before persistence.'],
    'POST platforms/reviews:/configs/prepare' => ['family' => 'review-platform-config', 'disposition' => 'deferred', 'case' => '', 'reason' => 'OAuth prepare handshake; external prepare action, not CRUD.'],
    'POST platforms/reviews:/configs/consume' => ['family' => 'review-platform-config', 'disposition' => 'deferred', 'case' => '', 'reason' => 'OAuth consume handshake; external result exchange, not local-only CRUD.'],
    'DELETE platforms/reviews:/configs' => ['family' => 'review-platform-config', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Disconnect is locally destructive but creation requires remote verification; no honest local round trip.'],
    'POST platforms/reviews:/' => ['family' => 'review-platform-config', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Credential verification fetches remote reviews before local persistence.'],
    'POST platforms/reviews:/configs/manually-sync-reviews' => ['family' => 'review-sync', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Manual external synchronization action; Phase 14 workflow.'],

    'POST platforms/feeds:/configs' => ['family' => 'feed-platform-config', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Credential verification invokes remote feed provider before persistence.'],
    'POST platforms/feeds:/configs/prepare' => ['family' => 'feed-platform-config', 'disposition' => 'deferred', 'case' => '', 'reason' => 'OAuth prepare handshake; external prepare action, not CRUD.'],
    'POST platforms/feeds:/configs/consume' => ['family' => 'feed-platform-config', 'disposition' => 'deferred', 'case' => '', 'reason' => 'OAuth consume handshake; external result exchange, not local-only CRUD.'],
    'DELETE platforms/feeds:/configs' => ['family' => 'feed-platform-config', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Disconnect cannot be paired with honest local creation without remote credential verification.'],

    'POST platforms:/dashboard-notices' => ['family' => 'dashboard-notice-state', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Writes pre-existing shared wpsr_statuses option; no uniquely owned fixed-option fixture is possible in current lab.'],

    'POST reviews:/' => ['family' => 'review', 'disposition' => 'round-trip', 'case' => 'review-crud', 'reason' => 'Creates owned review through REST.'],
    'POST reviews:/duplicate' => ['family' => 'review', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Bulk clone action, not create/update/delete lifecycle; cross-object breadth belongs later.'],
    'PUT reviews:/{id}' => ['family' => 'review', 'disposition' => 'round-trip', 'case' => 'review-crud', 'reason' => 'Updates exactly review_title; full row and pivots snapshotted.'],
    'DELETE reviews:/' => ['family' => 'review', 'disposition' => 'round-trip', 'case' => 'review-crud', 'reason' => 'Deletes owned review and relationships; gone asserted.'],
    'PUT reviews:/status-update' => ['family' => 'review', 'disposition' => 'covered-prior', 'case' => 'phase5-review-status', 'reason' => 'Phase 5 asserts every persisted status transition and route scope.'],
    'PUT reviews:/spam' => ['family' => 'review', 'disposition' => 'covered-prior', 'case' => 'phase5-review-status', 'reason' => 'Phase 5 asserts spam/not-spam persisted transitions and route scope.'],

    'POST testimonials:/' => ['family' => 'testimonial', 'disposition' => 'round-trip', 'case' => 'testimonial-crud', 'reason' => 'Creates owned testimonial through REST.'],
    'POST testimonials:/duplicate' => ['family' => 'testimonial', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Bulk clone action, not lifecycle update.'],
    'PUT testimonials:/{id}' => ['family' => 'testimonial', 'disposition' => 'round-trip', 'case' => 'testimonial-crud', 'reason' => 'Updates exactly review_title; full row and pivots snapshotted.'],
    'DELETE testimonials:/' => ['family' => 'testimonial', 'disposition' => 'round-trip', 'case' => 'testimonial-crud', 'reason' => 'Deletes owned testimonial and relationships; gone asserted.'],
    'PUT testimonials:/status-update' => ['family' => 'testimonial', 'disposition' => 'covered-prior', 'case' => 'phase5-testimonial-status', 'reason' => 'Phase 5 asserts every persisted status transition and route scope.'],
    'PUT testimonials:/spam' => ['family' => 'testimonial', 'disposition' => 'covered-prior', 'case' => 'phase5-testimonial-status', 'reason' => 'Phase 5 asserts spam/not-spam persisted transitions and route scope.'],

    'PUT reviews:/bulk-category' => ['family' => 'category', 'disposition' => 'round-trip', 'case' => 'category-crud', 'reason' => 'Creates category attached to owned review; relationship persisted and read.'],
    'PUT testimonials:/bulk-category' => ['family' => 'category-relationship', 'disposition' => 'covered-prior', 'case' => 'phase6-category-scope', 'reason' => 'Phase 6 value-tests testimonial-scoped attach and composite ownership.'],
    'PUT reviews/categories:/{id}' => ['family' => 'category', 'disposition' => 'round-trip', 'case' => 'category-crud', 'reason' => 'Renames one requested name; derived slug allowed; every other field snapshotted.'],
    'DELETE reviews/categories:/{id}' => ['family' => 'category', 'disposition' => 'round-trip', 'case' => 'category-crud', 'reason' => 'Deletes owned category; row and pivot absence asserted.'],
    'PUT testimonials/categories:/{id}' => ['family' => 'category', 'disposition' => 'alias', 'case' => 'category-crud', 'canonical' => 'PUT reviews/categories:/{id}', 'reason' => 'Alias mounts same global CategoriesController::update behind same CategoryManagementPolicy.'],
    'DELETE testimonials/categories:/{id}' => ['family' => 'category', 'disposition' => 'alias', 'case' => 'category-crud', 'canonical' => 'DELETE reviews/categories:/{id}', 'reason' => 'Alias mounts same global CategoriesController::delete behind same CategoryManagementPolicy.'],

    'PUT settings:/' => ['family' => 'platform-settings', 'disposition' => 'reversible', 'case' => 'platform-settings', 'reason' => 'YouTube local option write/read; exactly expiration changes in full snapshot.'],
    'DELETE settings:/' => ['family' => 'cache-clear', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Cache invalidation command, not settings deletion; cache ownership already covered in Phase 6.'],
    'DELETE settings:/twitter-card' => ['family' => 'reset', 'disposition' => 'deferred', 'case' => '', 'reason' => 'One-way fixed-option reset without route create/update/read lifecycle.'],
    'DELETE settings:/license' => ['family' => 'license', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Core delegates to add-on license filter and remote license server.'],
    'POST settings:/license' => ['family' => 'license', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Core delegates to add-on license filter and remote license server.'],
    'POST settings:/translations' => ['family' => 'translation-settings', 'disposition' => 'reversible', 'case' => 'translation-settings', 'reason' => 'Owned absent global option; create/read/update one translation/full snapshot/cleanup.'],
    'POST settings:/advance-settings' => ['family' => 'advanced-settings', 'disposition' => 'reversible', 'case' => 'advanced-settings', 'reason' => 'Owned absent global option; canonical save then one-field update/full snapshot/cleanup.'],
    'DELETE settings:/reset-images' => ['family' => 'reset', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Image/file reset action; filesystem and multi-platform destructive scope, not CRUD.'],
    'DELETE settings:/reset-error-log' => ['family' => 'reset', 'disposition' => 'deferred', 'case' => '', 'reason' => 'One-way shared error-log reset without matching route lifecycle.'],
    'DELETE settings:/delete-all-data' => ['family' => 'global-reset', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Destructive global uninstall reset explicitly prohibited.'],

    'POST chat-widgets:/' => ['family' => 'widget', 'disposition' => 'round-trip', 'case' => 'widget-crud', 'reason' => 'Creates owned widget through REST.'],
    'PUT chat-widgets:/' => ['family' => 'widget', 'disposition' => 'round-trip', 'case' => 'widget-crud', 'reason' => 'Updates exactly post_status; full post/meta snapshot asserted.'],
    'POST chat-widgets:/duplicate' => ['family' => 'widget', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Bulk clone action, not lifecycle update.'],
    'DELETE chat-widgets:/' => ['family' => 'widget', 'disposition' => 'round-trip', 'case' => 'widget-crud', 'reason' => 'Deletes owned widget; post and postmeta absence asserted.'],
    'PUT chat-widgets/meta/chats:/{id}' => ['family' => 'widget-config', 'disposition' => 'reversible', 'case' => 'widget-config', 'reason' => 'Canonical config save then one nested field change with full decoded config snapshot.'],
    'DELETE chat-widgets/meta/chats:/{id}/edit' => ['family' => 'widget-config-reset', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Editor reset action; resource deletion is covered by widget DELETE and config reset has no independent create/read contract.'],

    'PUT shoppable:/' => ['family' => 'shoppable-settings', 'disposition' => 'reversible', 'case' => 'shoppable-settings', 'reason' => 'Owned absent option; nested item update asserts complete option snapshot.'],
    'DELETE shoppable:/' => ['family' => 'shoppable-settings', 'disposition' => 'reversible', 'case' => 'shoppable-settings', 'reason' => 'Deletes one owned item semantically while preserving sibling settings; option cleanup follows.'],
    'PUT shoppable:/template-settings/{id}' => ['family' => 'feed-template-config', 'disposition' => 'reversible', 'case' => 'feed-template-config', 'reason' => 'Adds one shoppable feed field to owned YouTube template; full decoded meta snapshot.'],

    'POST notifications:/' => ['family' => 'notification', 'disposition' => 'round-trip', 'case' => 'notification-crud', 'reason' => 'Creates owned notification through REST.'],
    'PUT notifications:/' => ['family' => 'notification', 'disposition' => 'round-trip', 'case' => 'notification-crud', 'reason' => 'Updates exactly post_status; full post/meta snapshot asserted.'],
    'POST notifications:/duplicate' => ['family' => 'notification', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Bulk clone action, not lifecycle update.'],
    'DELETE notifications:/' => ['family' => 'notification', 'disposition' => 'round-trip', 'case' => 'notification-crud', 'reason' => 'Deletes owned notification; post and postmeta absence asserted.'],

    'POST onboarding:/' => ['family' => 'onboarding', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Workflow completion with volatile completed_at and shared session state; Phase 14 progression scope.'],
    'POST onboarding:/skip' => ['family' => 'onboarding', 'disposition' => 'deferred', 'case' => '', 'reason' => 'One-way workflow skip with volatile completed_at; Phase 14 progression scope.'],

    'POST templates:/' => ['family' => 'template', 'disposition' => 'round-trip', 'case' => 'template-crud', 'reason' => 'Creates owned review template through REST.'],
    'POST templates:/duplicate' => ['family' => 'template', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Bulk clone action, not lifecycle update.'],
    'DELETE templates:/' => ['family' => 'template', 'disposition' => 'round-trip', 'case' => 'template-crud', 'reason' => 'Deletes owned template; post and postmeta absence asserted.'],
    'PUT templates:/title/{id}' => ['family' => 'template', 'disposition' => 'round-trip', 'case' => 'template-crud', 'reason' => 'Updates exactly post_title; full post/meta snapshot asserted.'],
    'PUT templates/meta/reviews:/{id}' => ['family' => 'review-template-config', 'disposition' => 'reversible', 'case' => 'review-template-config', 'reason' => 'Canonical save then one nested field change with full decoded config snapshot.'],
    'POST templates/meta/reviews:/{id}/edit' => ['family' => 'review-template-preview', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Read-like editor preview over submitted settings; no intended persistence.'],
    'POST templates/meta/reviews:/{id}/load-more' => ['family' => 'review-template-preview', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Read-like pagination query over submitted settings; no intended persistence; aggregate covered Phase 7.'],
    'PUT templates/meta/feeds:/{id}' => ['family' => 'feed-template-config', 'disposition' => 'reversible', 'case' => 'feed-template-config', 'reason' => 'Canonical save then one nested feed field change with full decoded config snapshot.'],
    'POST templates/meta/feeds:/{id}/edit' => ['family' => 'feed-template-preview', 'disposition' => 'deferred', 'case' => '', 'reason' => 'Read-like editor preview that may query remote feed/cache; no intended persistence.'],
];

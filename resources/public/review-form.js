(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('.wpsr-review-form');
        forms.forEach(function (form) {
            initTurnstile(form);
            initRatingWidget(form);
            initMediaPreview(form);
            initFormSubmission(form);
        });
    });

    /**
     * Generate a UUID v4 for scoping an upload session.
     * Uses crypto.randomUUID() where available; falls back to crypto.getRandomValues()
     * so all parallel uploads in the first file-selection batch share the same ID
     * before any server response arrives.
     */
    function generateUploadSessionId() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var n = crypto.getRandomValues(new Uint8Array(1))[0] & 15;
            return (c === 'x' ? n : (n & 0x3 | 0x8)).toString(16);
        });
    }

    function getI18n(key, fallback) {
        if (typeof wpsrReviewForm !== 'undefined' && wpsrReviewForm.i18n && typeof wpsrReviewForm.i18n[key] === 'string') {
            return wpsrReviewForm.i18n[key];
        }

        return fallback;
    }

    var wpsrTurnstileBootstrapTimeout = 10000;
    var wpsrTurnstilePendingForms = [];
    var wpsrTurnstileScript = null;
    var wpsrTurnstileObserver = null;
    var wpsrTurnstileTimeoutId = null;

    function cleanupTurnstileBootstrapWatcher() {
        if (wpsrTurnstileObserver) {
            wpsrTurnstileObserver.disconnect();
            wpsrTurnstileObserver = null;
        }

        if (wpsrTurnstileTimeoutId) {
            clearTimeout(wpsrTurnstileTimeoutId);
            wpsrTurnstileTimeoutId = null;
        }
    }

    function flushTurnstileBootstrapFailure() {
        var pendingForms = wpsrTurnstilePendingForms.slice();

        wpsrTurnstilePendingForms = [];
        cleanupTurnstileBootstrapWatcher();

        pendingForms.forEach(function (pendingForm) {
            handleTurnstileBootstrapFailure(pendingForm.form);
        });
    }

    function markPendingTurnstileBootstrapFailure() {
        flushTurnstileBootstrapFailure();
    }

    function renderPendingTurnstileForms() {
        wpsrTurnstilePendingForms = wpsrTurnstilePendingForms.filter(function (pendingForm) {
            return !pendingForm.renderWidgets();
        });

        if (!wpsrTurnstilePendingForms.length) {
            cleanupTurnstileBootstrapWatcher();
            return true;
        }

        return false;
    }

    function attachSharedTurnstileScript() {
        var script = document.querySelector('script[src*="challenges.cloudflare.com/turnstile/"]');
        if (!script || script === wpsrTurnstileScript) {
            return;
        }

        wpsrTurnstileScript = script;

        if (script.dataset.loaded === 'true' || (window.turnstile && typeof window.turnstile.render === 'function')) {
            script.dataset.loaded = 'true';
            renderPendingTurnstileForms();
            return;
        }

        script.addEventListener('load', function () {
            script.dataset.loaded = 'true';
            renderPendingTurnstileForms();
        }, { once: true });
        script.addEventListener('error', function () {
            flushTurnstileBootstrapFailure();
        }, { once: true });
    }

    function ensureSharedTurnstileBootstrapWatcher() {
        attachSharedTurnstileScript();

        if (renderPendingTurnstileForms()) {
            return;
        }

        if (!wpsrTurnstileObserver) {
            wpsrTurnstileObserver = new MutationObserver(function () {
                attachSharedTurnstileScript();
                renderPendingTurnstileForms();
            });

            wpsrTurnstileObserver.observe(document.head || document.documentElement, {
                childList: true,
                subtree: true
            });
        }

        if (!wpsrTurnstileTimeoutId) {
            wpsrTurnstileTimeoutId = setTimeout(function () {
                markPendingTurnstileBootstrapFailure();
            }, wpsrTurnstileBootstrapTimeout);
        }
    }

    function initTurnstile(form) {
        var widgets = form.querySelectorAll('.cf-turnstile');
        if (!widgets.length) {
            return;
        }

        function renderWidgets() {
            if (!window.turnstile || typeof window.turnstile.ready !== 'function' || typeof window.turnstile.render !== 'function') {
                return false;
            }

            if (form.dataset.turnstileBootstrapFailed === '1') {
                delete form.dataset.turnstileBootstrapFailed;
            }

            var submitBtn = form.querySelector('.wpsr-review-form-btn');
            if (submitBtn && !form.classList.contains('wpsr-form-loading')) {
                submitBtn.disabled = false;
            }

            var messagesEl = form.querySelector('.wpsr-review-form-messages');
            if (messagesEl && messagesEl.dataset.wpsrTurnstileFailure === '1') {
                messagesEl.innerHTML = '';
                delete messagesEl.dataset.wpsrTurnstileFailure;
            }

            window.turnstile.ready(function () {
                widgets.forEach(function (widget) {
                    if (widget.dataset.widgetId) {
                        return;
                    }

                    var widgetId = window.turnstile.render(widget, {
                        sitekey: widget.getAttribute('data-sitekey') || '',
                        theme: widget.getAttribute('data-theme') || 'auto',
                        appearance: widget.getAttribute('data-appearance') || 'always',
                        action: widget.getAttribute('data-action') || 'wpsr_review_form_submit'
                    });

                    widget.dataset.widgetId = widgetId;
                });
            });

            return true;
        }

        if (renderWidgets()) {
            return;
        }

        if (!wpsrTurnstilePendingForms.some(function (pendingForm) {
            return pendingForm.form === form;
        })) {
            wpsrTurnstilePendingForms.push({
                form: form,
                renderWidgets: renderWidgets
            });
        }

        ensureSharedTurnstileBootstrapWatcher();
    }

    function handleTurnstileBootstrapFailure(form) {
        form.dataset.turnstileBootstrapFailed = '1';

        var submitBtn = form.querySelector('.wpsr-review-form-btn');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        var messagesEl = form.querySelector('.wpsr-review-form-messages');
        if (messagesEl) {
            messagesEl.dataset.wpsrTurnstileFailure = '1';
            showMessage(messagesEl, getI18n('captchaLoadFailed', 'Captcha could not load. Please refresh the page and try again.'), 'error');
        }
    }

    function resetTurnstile(form) {
        if (!window.turnstile || typeof window.turnstile.reset !== 'function') {
            return;
        }

        form.querySelectorAll('.cf-turnstile').forEach(function (widget) {
            var widgetId = widget.dataset.widgetId;
            if (widgetId) {
                window.turnstile.reset(widgetId);
            }
        });
    }

    function initRatingWidget(form) {
        var ratingInputs = form.querySelectorAll('.wpsr-rating-input');

        ratingInputs.forEach(function (container) {
            var icons = container.querySelectorAll('.wpsr-rating-icon');
            var hiddenInput = container.parentElement.querySelector('.wpsr-rating-value');

            icons.forEach(function (icon) {
                // Keyboard accessibility
                icon.setAttribute('tabindex', '0');
                icon.setAttribute('role', 'radio');
                icon.setAttribute('aria-checked', 'false');
                icon.setAttribute('aria-label', 'Rating ' + icon.getAttribute('data-value'));

                // Hover
                icon.addEventListener('mouseenter', function () {
                    var val = parseInt(icon.getAttribute('data-value'));
                    highlightIcons(icons, val, 'wpsr-rating-hover');
                });

                // Mouse leave
                icon.addEventListener('mouseleave', function () {
                    clearClass(icons, 'wpsr-rating-hover');
                });

                // Click
                icon.addEventListener('click', function () {
                    selectRating(icons, icon, hiddenInput);
                });

                // Keyboard support
                icon.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        selectRating(icons, icon, hiddenInput);
                    }
                });
            });
        });
    }

    function selectRating(icons, selectedIcon, hiddenInput) {
        var val = parseInt(selectedIcon.getAttribute('data-value'));
        hiddenInput.value = val;
        clearClass(icons, 'wpsr-rating-active');
        highlightIcons(icons, val, 'wpsr-rating-active');

        // Update aria-checked
        icons.forEach(function (icon) {
            var iconVal = parseInt(icon.getAttribute('data-value'));
            icon.setAttribute('aria-checked', iconVal <= val ? 'true' : 'false');
        });
    }

    function highlightIcons(icons, upToValue, className) {
        icons.forEach(function (icon) {
            var val = parseInt(icon.getAttribute('data-value'));
            if (val <= upToValue) {
                icon.classList.add(className);
            } else {
                icon.classList.remove(className);
            }
        });
    }

    function clearClass(icons, className) {
        icons.forEach(function (icon) {
            icon.classList.remove(className);
        });
    }

    function initMediaPreview(form) {
        var fileInputs = form.querySelectorAll('.wpsr-field-media input[type="file"]');

        fileInputs.forEach(function (input) {
            var preview = input.parentElement.querySelector('.wpsr-media-preview');
            if (!preview) return;

            // Track {fileUrl, token, previewEl} for files successfully uploaded to the server.
            // Only these will be included in the form submission — no binary data sent on submit.
            var uploadedFiles  = [];
            // Count uploads that are in-flight but not yet resolved.
            // Combined with uploadedFiles.length this enforces the cap across concurrent selections.
            var pendingUploads = 0;
            // Client-generated UUID that scopes all uploads for this field to one session
            // lifecycle. Generated eagerly so every request in the first parallel batch
            // carries the same ID — no server round-trip needed before uploads begin.
            var uploadSessionId = generateUploadSessionId();

            var notice = document.createElement('p');
            notice.className = 'wpsr-media-notice';
            preview.parentElement.appendChild(notice);

            function addPreviewItem(objectUrl, fileUrl) {
                var wrapper = document.createElement('span');
                wrapper.className = 'wpsr-media-preview-item';

                var img = document.createElement('img');
                img.src = objectUrl;

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'wpsr-media-remove';
                removeBtn.setAttribute('aria-label', 'Remove image');
                removeBtn.textContent = '\u00D7';
                removeBtn.addEventListener('click', function () {
                    wrapper.remove();
                    // If the upload is still in flight, abort it so the pending slot
                    // is freed immediately rather than after the network request finishes.
                    if (typeof wrapper._abort === 'function') {
                        wrapper._abort();
                    }
                    uploadedFiles = uploadedFiles.filter(function (f) {
                        return f.fileUrl !== fileUrl;
                    });
                });

                wrapper.appendChild(img);
                wrapper.appendChild(removeBtn);
                preview.appendChild(wrapper);
                return wrapper;
            }

            input.addEventListener('change', function () {
                var ajaxUrl      = (typeof wpsrReviewForm !== 'undefined' && wpsrReviewForm.ajaxUrl) ? wpsrReviewForm.ajaxUrl : '';
                // Fallback to the known constant so a localization timing gap does not silently drop uploads.
                var uploadAction = (typeof wpsrReviewForm !== 'undefined' && wpsrReviewForm.uploadAction) ? wpsrReviewForm.uploadAction : 'wpsr_review_form_upload';
                var formId       = form.getAttribute('data-form-id');
                var nonce        = form.querySelector('[name="_wpsr_nonce"]');
                var maxFiles     = parseInt(input.getAttribute('data-max-files') || 3, 10);
                var maxSize      = parseInt(input.getAttribute('data-max-size') || 5, 10);
                var newFiles     = Array.from(input.files);
                notice.textContent = '';

                newFiles.forEach(function (file) {
                    if ((uploadedFiles.length + pendingUploads) >= maxFiles) {
                        notice.textContent = getI18n('maxImagesAllowed', 'Maximum %d image(s) allowed.').replace('%d', maxFiles);
                        return;
                    }
                    if (file.size > maxSize * 1024 * 1024) {
                        notice.textContent = getI18n('fileSizeExceeded', 'Some files exceed the %dMB size limit.').replace('%d', maxSize);
                        return;
                    }

                    pendingUploads++;

                    // Show placeholder preview while uploading
                    var objectUrl  = URL.createObjectURL(file);
                    var controller = new AbortController();
                    var wrapper    = addPreviewItem(objectUrl, null);
                    wrapper.classList.add('wpsr-media-uploading');
                    // Expose abort so the remove button can free the slot immediately.
                    wrapper._abort = function () { controller.abort(); };

                    var formData = new FormData();
                    formData.append('action',      uploadAction);
                    formData.append('form_id',     formId);
                    formData.append('_wpsr_nonce', nonce ? nonce.value : '');
                    formData.append('file',        file);
                    // Always send the session ID — it is generated before any upload fires,
                    // so parallel first-batch requests all share the same bucket.
                    formData.append('upload_session_id', uploadSessionId);

                    fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin', signal: controller.signal })
                        .then(function (r) { return r.json(); })
                        .then(function (result) {
                            wrapper.classList.remove('wpsr-media-uploading');
                            if (result.success && result.data && result.data.files && result.data.files[0]) {
                                // User may have clicked Remove while the upload was in flight.
                                // If the wrapper is no longer in the DOM, discard the result silently.
                                if (!wrapper.isConnected) {
                                    return;
                                }
                                var fileUrl  = result.data.files[0].file;
                                var token    = result.data.files[0].token || '';
                                uploadedFiles.push({ fileUrl: fileUrl, token: token, previewEl: wrapper });
                                // Rebind remove so it uses the real fileUrl
                                wrapper.querySelector('.wpsr-media-remove').addEventListener('click', function () {
                                    uploadedFiles = uploadedFiles.filter(function (f) {
                                        return f.fileUrl !== fileUrl;
                                    });
                                });
                            } else {
                                wrapper.remove();
                                var errMsg = (result.data && result.data.errors && result.data.errors.file)
                                    ? result.data.errors.file[0]
                                    : getI18n('uploadFailed', 'Upload failed.');
                                notice.textContent = errMsg;
                            }
                        })
                        .catch(function (err) {
                            // AbortError means the user removed the preview; no error message needed.
                            if (err && err.name === 'AbortError') return;
                            wrapper.remove();
                            notice.textContent = getI18n('uploadRetry', 'Upload failed. Please try again.');
                        })
                        .finally(function () {
                            pendingUploads--;
                            URL.revokeObjectURL(objectUrl);
                        });
                });

                // Reset the file input so the same file can be re-selected if needed
                input.value = '';
            });

            // Expose uploaded URLs for the submit handler (kept for back-compat)
            preview._getUploadedUrls = function () {
                return uploadedFiles.map(function (f) { return f.fileUrl; });
            };

            // Expose signed tokens for the submit handler — preferred over raw URLs
            preview._getUploadedTokens = function () {
                return uploadedFiles.map(function (f) { return f.token; });
            };

            // Expose the upload session ID for inclusion in the form submission.
            preview._getSessionId = function () {
                return uploadSessionId;
            };

            // Expose reset so the submit handler can clear state after success.
            // A fresh session ID is generated so any re-upload after reset starts a
            // new isolated bucket rather than reusing the claimed session.
            preview._reset = function () {
                uploadedFiles   = [];
                uploadSessionId = generateUploadSessionId();
                preview.innerHTML = '';
                notice.textContent = '';
            };
        });
    }

    function initFormSubmission(form) {
        // Prevents double-submission from rapid clicks before the button is disabled.
        var wpsrSubmitting = false;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (wpsrSubmitting) return;
            wpsrSubmitting = true;
            setTimeout(function () { wpsrSubmitting = false; }, 1500);

            var formId = form.getAttribute('data-form-id');
            var messagesEl = form.querySelector('.wpsr-review-form-messages');
            var submitBtn = form.querySelector('.wpsr-review-form-btn');

            if (!formId || !messagesEl || !submitBtn) return;

            // Clear previous messages
            messagesEl.innerHTML = '';

            // Block submit while a file is still being uploaded
            // ff_uploading check that prevents submitting with incomplete file data.
            if (form.querySelector('.wpsr-media-uploading')) {
                showMessage(messagesEl, getI18n('uploadInProgress', 'File upload in progress. Please wait...'), 'error');
                wpsrSubmitting = false;
                return;
            }

            // Validate rating
            var ratingInput = form.querySelector('.wpsr-rating-value[required]');
            if (ratingInput && !ratingInput.value) {
                showMessage(messagesEl, getI18n('ratingRequired', 'Please select a rating.'), 'error');
                wpsrSubmitting = false;
                return;
            }

            if (form.dataset.turnstileBootstrapFailed === '1') {
                showMessage(messagesEl, getI18n('captchaLoadFailed', 'Captcha could not load. Please refresh the page and try again.'), 'error');
                wpsrSubmitting = false;
                return;
            }

            // Disable button
            form.classList.add('wpsr-form-loading');
            submitBtn.disabled = true;
            var originalText = submitBtn.textContent;
            submitBtn.textContent = getI18n('submitting', 'Submitting...');

            var ajaxUrl = (typeof wpsrReviewForm !== 'undefined' && wpsrReviewForm.ajaxUrl)
                ? wpsrReviewForm.ajaxUrl
                : '';
            // Fallback to the known constant — this value never changes across deployments,
            // so a localization timing gap between base and pro plugin releases does not
            // hard-stop form submission in the browser.
            var action = (typeof wpsrReviewForm !== 'undefined' && wpsrReviewForm.action)
                ? wpsrReviewForm.action
                : 'wpsr_submit_review_form';

            if (!ajaxUrl) {
                showMessage(messagesEl, getI18n('configurationError', 'Configuration error. Please try again later.'), 'error');
                resetButton(form, submitBtn, originalText);
                return;
            }

            // Collect signed upload tokens per media field into a JSON map submitted
            // as a separate body field — avoids relying on parse_str bracket-notation
            // handling for multi-file tokens inside the URL-encoded data blob.
            var mediaTokenMap = {};
            form.querySelectorAll('.wpsr-field-media input[type="file"]').forEach(function (fileInput) {
                var fieldName = fileInput.getAttribute('name');
                if (!fieldName) return;
                var previewEl = fileInput.parentElement.querySelector('.wpsr-media-preview');
                if (previewEl && typeof previewEl._getUploadedTokens === 'function') {
                    var tokens = previewEl._getUploadedTokens();
                    if (tokens.length) {
                        mediaTokenMap[fieldName] = tokens;
                    }
                }
            });

            var params = new URLSearchParams();
            form.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (!el.name || el.disabled || el.type === 'file') return;
                if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
                params.append(el.name, el.value);
            });

            var body = new FormData();
            body.append('data', params.toString());
            if (Object.keys(mediaTokenMap).length) {
                body.append('wpsr_media_tokens', JSON.stringify(mediaTokenMap));
            }

            // Include the upload session ID so the server can claim the correct
            // per-session orphan-tracking bucket on successful submission.
            form.querySelectorAll('.wpsr-field-media input[type="file"]').forEach(function (fileInput) {
                var previewEl = fileInput.parentElement.querySelector('.wpsr-media-preview');
                if (previewEl && typeof previewEl._getSessionId === 'function') {
                    var sid = previewEl._getSessionId();
                    if (sid) {
                        body.append('wpsr_upload_session_id', sid);
                    }
                }
            });
            body.append('action', action);
            body.append('form_id', formId);

            fetch(ajaxUrl + '?t=' + Date.now(), {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                // Gate on the WP AJAX JSON envelope (success: true) rather than HTTP status.
                // wp_send_json_success()  → { success: true,  data: { message: '...' } }
                // wp_send_json()/errors   → { errors: { field: ['msg'] } }  (non-2xx)
                if (result && result.success === true) {
                    var msg = (result.data && result.data.message) || getI18n('thankYou', 'Thank you for your review!');
                    showMessage(messagesEl, msg, 'success');
                    form.reset();
                    resetTurnstile(form);
                    // Clear image previews and accumulated file state
                    form.querySelectorAll('.wpsr-media-preview').forEach(function (p) {
                        if (typeof p._reset === 'function') {
                            p._reset();
                        } else {
                            p.innerHTML = '';
                        }
                    });
                    // Clear rating active states and aria
                    form.querySelectorAll('.wpsr-rating-active').forEach(function (icon) {
                        icon.classList.remove('wpsr-rating-active');
                        icon.setAttribute('aria-checked', 'false');
                    });
                } else {
                    // Both logical failures and non-2xx HTTP errors carry errors
                    // at result.errors: { field: ['msg'] }
                    var errors = (result && result.errors) || {};
                    var errorItems = [];
                    Object.keys(errors).forEach(function (field) {
                        var msgs = errors[field];
                        if (Array.isArray(msgs)) {
                            msgs.forEach(function (m) { errorItems.push(m); });
                        }
                    });
                    var errorMsg = errorItems.length
                        ? errorItems[0]
                        : getI18n('genericError', 'Something went wrong. Please try again.');
                    showMessage(messagesEl, errorMsg, 'error', errorItems.slice(1));
                }
            })
            .catch(function () {
                showMessage(messagesEl, getI18n('networkError', 'Network error. Please check your connection and try again.'), 'error');
            })
            .finally(function () {
                resetButton(form, submitBtn, originalText);
            });
        });
    }

    function resetButton(form, btn, text) {
        form.classList.remove('wpsr-form-loading');
        btn.disabled = false;
        btn.textContent = text;
    }

    function showMessage(container, msg, type, errorItems) {
        var div = document.createElement('div');
        div.className = 'wpsr-review-form-message-' + type;
        if (msg) {
            var messageText = document.createElement('span');
            messageText.textContent = msg;
            div.appendChild(messageText);
        }
        if (Array.isArray(errorItems) && errorItems.length) {
            var ul = document.createElement('ul');
            errorItems.forEach(function (item) {
                var li = document.createElement('li');
                li.textContent = item;
                ul.appendChild(li);
            });
            div.appendChild(ul);
        }
        container.innerHTML = '';
        container.appendChild(div);
    }
})();

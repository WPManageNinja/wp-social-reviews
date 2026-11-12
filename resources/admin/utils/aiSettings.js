const WPSR_AI_API_KEY_MASK_PREFIX = '****';

/**
 * Whether a value is the server's mask for a stored API key. Mirrors
 * Helper::isMaskedApiKey(), which reads it as "keep the stored key".
 */
function wpsrIsMaskedApiKey(wpsrValue) {
  return typeof wpsrValue === 'string' && wpsrValue.indexOf(WPSR_AI_API_KEY_MASK_PREFIX) === 0;
}

/**
 * The platform's model when it offers exactly one, otherwise null. That platform
 * shows a disabled select, so without this the feature never counts as configured.
 */
function wpsrAiOnlyModel(wpsrPlatformEntry) {
  const wpsrModels = (wpsrPlatformEntry && wpsrPlatformEntry.models) || [];

  return wpsrModels.length === 1 ? wpsrModels[0].value ?? null : null;
}

/**
 * Credentials after the AI platform changes. A key belongs to its platform, so another
 * platform blanks the fields. The saved platform gets them back, or the next save clears a valid key.
 */
function wpsrAiCredentialsForPlatform(wpsrPlatform, wpsrSavedConfig, wpsrPlatformEntry) {
  const wpsrSaved = wpsrSavedConfig || {};
  const wpsrIsSavedPlatform =
    !!wpsrSaved.platform &&
    !!wpsrPlatform &&
    String(wpsrPlatform).toLowerCase() === String(wpsrSaved.platform).toLowerCase();

  if (!wpsrIsSavedPlatform) {
    return { selected_model: wpsrAiOnlyModel(wpsrPlatformEntry), ai_api_key: null };
  }

  return {
    selected_model: wpsrSaved.model ?? wpsrAiOnlyModel(wpsrPlatformEntry) ?? null,
    ai_api_key: wpsrSaved.api_key ?? null
  };
}

/**
 * Whether a platform asks for an API key. One that authenticates elsewhere declares
 * requires_api_key: false. Older catalogues omit the flag, so absence means a key is needed.
 */
function wpsrAiPlatformNeedsApiKey(wpsrPlatform) {
  return !wpsrPlatform || wpsrPlatform.requires_api_key !== false;
}

/**
 * The platform's help link, or null. The catalogue arrives through a WordPress filter,
 * so only http(s) and site-relative URLs are accepted.
 */
function wpsrAiNoticeLink(wpsrPlatform) {
  const wpsrLink = wpsrPlatform && wpsrPlatform.notice_link;

  if (!wpsrLink || !wpsrLink.url || !wpsrLink.label) {
    return null;
  }

  return /^(https?:\/\/|\/)/i.test(String(wpsrLink.url)) ? wpsrLink : null;
}

/**
 * The platform's models the select offers. Retired ones are left out, except the saved one,
 * which stays (disabled) so the field never reads blank.
 */
function wpsrAiOfferedModels(wpsrPlatformEntry, wpsrSavedModel) {
  const wpsrModels = (wpsrPlatformEntry && wpsrPlatformEntry.models) || [];

  return wpsrModels
    .filter((wpsrModel) => wpsrModel.status !== 'retired' || wpsrModel.value === wpsrSavedModel)
    .map((wpsrModel) => (wpsrModel.status === 'retired' ? { ...wpsrModel, disabled: true } : wpsrModel));
}

/**
 * 'default' when the platform offers a Default model option, otherwise null.
 */
function wpsrAiDefaultModel(wpsrPlatformEntry) {
  const wpsrModels = (wpsrPlatformEntry && wpsrPlatformEntry.models) || [];

  return wpsrModels.some((wpsrModel) => wpsrModel.value === 'default') ? 'default' : null;
}

module.exports = {
  WPSR_AI_API_KEY_MASK_PREFIX,
  wpsrIsMaskedApiKey,
  wpsrAiOfferedModels,
  wpsrAiDefaultModel,
  wpsrAiOnlyModel,
  wpsrAiCredentialsForPlatform,
  wpsrAiPlatformNeedsApiKey,
  wpsrAiNoticeLink
};

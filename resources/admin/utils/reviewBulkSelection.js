const wpsrBulkDeleteReviewPlatforms = [
  'custom',
  'fluent-cart',
  'fluent_forms',
  'woocommerce',
  'native_form'
];

function wpsrCanBulkDeleteReview(wpsrReview) {
  return !!wpsrReview && wpsrBulkDeleteReviewPlatforms.includes(wpsrReview.platform_name);
}

function wpsrFilterBulkDeleteReviews(wpsrReviews) {
  if (!Array.isArray(wpsrReviews)) {
    return [];
  }

  return wpsrReviews.filter(wpsrCanBulkDeleteReview);
}

module.exports = {
  wpsrBulkDeleteReviewPlatforms,
  wpsrCanBulkDeleteReview,
  wpsrFilterBulkDeleteReviews
};

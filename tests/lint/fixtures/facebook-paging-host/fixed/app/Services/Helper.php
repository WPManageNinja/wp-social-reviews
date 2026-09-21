<?php
/** Minimal faithful Phase 24 copy from app/Services/Helper.php. */
class Phase24Fixture
{
    function fetchFacebookManagedPages($accessToken, $limit = 500, $platform = '')
    {
        if (empty($accessToken)) {
            throw new \Exception(esc_html__('Facebook access token is missing.', 'wp-social-reviews'));
        }

        $limit = absint($limit) ?: 500;
        $url   = 'https://graph.facebook.com/me?fields=accounts.limit(' . $limit . '),id,name&access_token=' . $accessToken;

        $accountId = null;
        $pages     = [];
        $nextUrl   = null;
        $isFirst   = true;

        // Hard ceiling on pagination so a large account or a repeating `next` cursor can never
        // make the connect/consume request hang or grow $pages without bound. 20 requests at the
        // 500-page default covers ~10k managed pages — far beyond any real account.
        $maxRequests = 20;
        $requests    = 0;

        do {
            if (++$requests > $maxRequests) {
                throw new \Exception(esc_html__(
                    'This account manages too many Facebook pages to load automatically. Please reconnect and select pages manually.',
                    'wp-social-reviews'
                ));
            }

            $result   = static::requestFacebookGraph($nextUrl ?: $url, $platform);
            $response = $result['response'];

            if (is_wp_error($response)) {
                throw new \Exception(esc_html($response->get_error_message()));
            }

            $body = $result['body'];

            if (!empty(Arr::get($body, 'error'))) {
                throw new \Exception(esc_html(
                    Arr::get($body, 'error.message', __('Unable to fetch Facebook pages.', 'wp-social-reviews'))
                ));
            }

            // The first call returns { id, name, accounts: { data, paging } };
            // each paging.next URL returns the accounts payload ({ data, paging }) directly.
            if ($isFirst) {
                $accountId = Arr::get($body, 'id');
                $accounts  = Arr::get($body, 'accounts', []);
                $isFirst   = false;
            } else {
                $accounts = is_array($body) ? $body : [];
            }

            $data = Arr::get($accounts, 'data', []);
            if (is_array($data)) {
                foreach ($data as $page) {
                    if (Arr::get($page, 'id') && Arr::get($page, 'name')) {
                        $pages[] = $page;
                    }
                }
            }

            // Defense-in-depth: only follow the paging cursor when it stays on Facebook's Graph
            // host, so a manipulated `paging.next` can never redirect the loop to another host.
            $nextUrl = Arr::get($accounts, 'paging.next');
            if ($nextUrl && strtolower((string) wp_parse_url($nextUrl, PHP_URL_HOST)) !== 'graph.facebook.com') {
                $nextUrl = null;
            }
        } while ($nextUrl);

        return [
            'account_id' => $accountId,
            'pages'      => $pages,
        ];
    }
}

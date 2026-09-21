# WP Social Ninja – Social Feed, Reviews & Chat Plugin for WP (Google Reviews, Photo Feeds & More)

#### Run the project

- `npm install`
- `npx mix watch` or `npm run watch`
- `npx mix --production`

##### Run the tests
If you don't have a testing database then please run the following command from your project root:
- ` bin/install-wp-tests.sh db_name db_user db_pass db_host`

You may need to install `phpunit` package as dev deps. To run the tests,
just run `phpunit` command in the terminal from project root. To get more
details on `phpunit` type `phpunit --help` on your terminal. You can test
both free and pro version at the same time. All test file names should start
with `test-`. Make sure you have all your test file in the `tests` folder.
You can include or exclude your files in `phpunit.xml.dist` file.


### Build for release
`sh ./build.sh --node-build --with-pro`


### Bridge auth secrets (token refresh)

OAuth token refresh runs through the `wpsn-auth` bridge (`RemoteAuth::refreshAuth()`), so the
provider client secrets live **only on the bridge**, never in this plugin. Each request is signed
with a **per-site** secret using HMAC-SHA256 over `"{timestamp}.{canonical_json(body)}"`.

**Where secrets are stored — and why a shipped constant is never safe:**

| Location | Distributed in the zip? | Use for secrets? |
|----------|-------------------------|------------------|
| Plugin source (e.g. `wp-social-reviews.php`) | **Yes** — public, extractable | **Never.** A "secret" here is readable by anyone and forgeable. |
| `wp-config.php` constant | No — per-install, server-only | Yes (same trust level as the DB password / WP salts) |
| Database (`wp_options`, encrypted) | No — per-install, server-only | Yes |

**Per-site model (the default, for the distributed plugin):**

- **Plugin →** stores `{ site_id, site_secret }` in the `wpsr_auth_bridge_identity` option
  (`site_secret` **encrypted at rest**, autoload off). It is **provisioned automatically** during
  the OAuth connect handshake — the bridge returns it in the encrypted result and
  `RemoteAuth::storeBridgeIdentity()` persists it. Nothing is hardcoded; the secret is **unique
  per site**.
- **Bridge →** holds a single `WPSN_AUTH_MASTER_SECRET` as a **server env var** (never in the
  plugin) and derives each site's secret on the fly: `site_secret = HMAC(site_id, MASTER)`. The
  bridge stores nothing per-site.
- **No fleet-wide shared secret exists.** A leak of one site's DB exposes only that one site.
- Refresh requests send `X-WPSN-Site-Id` (a public identifier, not a secret) so the bridge knows
  which key to re-derive. If a site has no stored identity (e.g. connected before this existed),
  refresh returns a "reconnect" error and the user re-establishes it on the next connect — no
  unsigned requests are ever sent.

**Self-hosted / single-site override (optional):** an operator running both the bridge and one
site can set a matching pair by hand in **`wp-config.php`** (server-only, not shipped). This is the
**only** scenario with a "same on both sides" value:

```php
// wp-config.php — self-hosted single site only, NEVER in distributed plugin source
define('WPSN_AUTH_SITE_ID', 'https://my-site.com');
define('WPSN_AUTH_REFRESH_SECRET', '…'); // = HMAC(site_id, MASTER), computed once on the bridge
```

Verify the signing contract (canonical JSON + a per-site test vector to cross-check the bridge):

```bash
php dev/scripts/verify-refresh-signing.php
```

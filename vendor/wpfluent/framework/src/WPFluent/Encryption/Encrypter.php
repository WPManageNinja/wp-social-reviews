<?php

namespace WPSocialReviews\Framework\Encryption;

use LogicException;
use RuntimeException;
use InvalidArgumentException;
use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\Framework\Encryption\EncryptException;
use WPSocialReviews\Framework\Encryption\DecryptException;

class Encrypter
{
    /**
     * The encryption key.
     *
     * @var string
     */
    protected $key;

    /**
     * The algorithm used for encryption.
     *
     * @var string
     */
    protected $cipher;

    /**
     * The plugin slug key.
     *
     * @var string
     */
    protected $slug;

    /**
     * Where the key came from: given, constant or database.
     *
     * @var string
     */
    protected $keySource = 'given';

    /**
     * The payload version encrypt() writes and decrypt() reads. A payload
     * without one is version 1.
     */
    const PAYLOAD_VERSION = 1;

    /**
     * Seconds after which a rotation lock left by a request that died is
     * taken over.
     */
    const ROTATION_LOCK_SECONDS = 60;

    /**
     * The supported cipher algorithms and their properties.
     *
     * @var array
     */
    private static $supportedCiphers = [
        'aes-128-cbc' => ['size' => 16, 'aead' => false],
        'aes-256-cbc' => ['size' => 32, 'aead' => false],
        'aes-128-gcm' => ['size' => 16, 'aead' => true],
        'aes-256-gcm' => ['size' => 32, 'aead' => true],
    ];

    /**
     * Create a new encrypter instance.
     *
     * Without a key it uses the {SLUG}_ENCRYPTION_KEY constant, else a key
     * stored in the database (made on first use). A key written as
     * base64:... is decoded. app.key ships with the plugin, so it's the same
     * on every site: it is only tried when decrypting, never to encrypt.
     *
     * @param  string|null  $key
     * @param  string  $cipher
     * @return void
     *
     * @throws \RuntimeException
     */
    public function __construct($key = null, $cipher = 'aes-128-cbc')
    {
    	$this->cipher = $cipher;

        $this->slug = $this->getSlug();

        if ($key === null || $key === '') {
            [$key, $this->keySource] = $this->resolveKey();
        } else {
            $key = static::parseKey($key);
        }

        if (!static::supported($key, $this->cipher)) {
            $ciphers = implode(', ', array_keys(self::$supportedCiphers));

            throw new RuntimeException("Unsupported cipher or incorrect key length. Supported ciphers are: {$ciphers}.");
        }

        $this->key = $key;
    }

    /**
     * A key as written: base64:... is decoded, anything else is used as it is.
     *
     * @param  string  $key
     * @return string
     *
     * @throws \RuntimeException For a base64: key that isn't valid base64.
     */
    public static function parseKey($key)
    {
        if (!is_string($key) || strncmp($key, 'base64:', 7) !== 0) {
            return $key;
        }

        $decoded = base64_decode(substr($key, 7), true);

        if ($decoded === false) {
            throw new RuntimeException('The encryption key starts with base64: but is not valid base64.');
        }

        return $decoded;
    }

    /**
     * The key when none is given, and where it came from.
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveKey()
    {
        $constant = $this->keyConstant();

        if ($constant && defined($constant) && ($value = constant($constant))) {
            return [static::parseKey($value), 'constant'];
        }

        return [$this->getStoredKey(), 'database'];
    }

    /**
     * Where the key came from: given, constant or database.
     *
     * @return string
     */
    public function keySource()
    {
        return $this->keySource;
    }

    /**
     * The name of the constant a site can define the key with, such as
     * MY_PLUGIN_ENCRYPTION_KEY for the slug my-plugin. The
     * {slug}.encryption.key_constant filter changes it.
     *
     * @return string|null
     */
    public function keyConstant()
    {
        $app = App::getInstance();

        if (!$app) {
            return null;
        }

        $slug = (string) $app->config->get('app.slug');

        $default = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $slug)) . '_ENCRYPTION_KEY';

        return $app->applyFilters($slug . '.encryption.key_constant', $default);
    }

    /**
     * Determine if the given key and cipher combination is valid.
     *
     * @param  string  $key
     * @param  string  $cipher
     * @return bool
     */
    public static function supported($key, $cipher)
    {
        if (!isset(self::$supportedCiphers[strtolower($cipher)])) {
            return false;
        }

        return mb_strlen(
        	$key, '8bit'
        ) === self::$supportedCiphers[strtolower($cipher)]['size'];
    }

    /**
     * Create a new encryption key for the given cipher.
     *
     * @param  string  $cipher
     * @return string
     *
     * @throws \RuntimeException If the cipher is not in the supported list.
     */
    public static function generateKey($cipher)
    {
        $cipher = strtolower($cipher);

        if (!isset(self::$supportedCiphers[$cipher])) {
            $ciphers = implode(', ', array_keys(self::$supportedCiphers));

            throw new RuntimeException(
                "Unsupported cipher '{$cipher}'. Supported ciphers are: {$ciphers}."
            );
        }

        return random_bytes(self::$supportedCiphers[$cipher]['size']);
    }

    /**
     * Encrypt the given value.
     *
     * @param  mixed  $value
     * @param  bool  $serialize
     * @return string
     *
     * @throws \WPSocialReviews\Framework\Encryption\EncryptException
     */
    public function encrypt($value, $serialize = true)
    {
        $iv = random_bytes(openssl_cipher_iv_length(strtolower($this->cipher)));

        $value = \openssl_encrypt(
            $serialize ? serialize($value) : $value,
            strtolower($this->cipher), $this->key, 0, $iv, $tag
        );

        if ($value === false) {
            throw new EncryptException('Could not encrypt the data.');
        }

        $iv = base64_encode($iv);
        $tag = base64_encode($tag ?? '');

        $mac = self::$supportedCiphers[strtolower($this->cipher)]['aead']
            ? '' // For AEAD-algorithms, the tag / MAC is returned by openssl_encrypt...
            : $this->hash($iv, $value, $this->key);

        $v = static::PAYLOAD_VERSION;

        $json = json_encode(compact('iv', 'value', 'mac', 'tag', 'v'), JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new EncryptException('Could not encrypt the data.');
        }

        return base64_encode($json);
    }

    /**
     * Encrypt a string without serialization.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \WPSocialReviews\Framework\Encryption\EncryptException
     */
    public function encryptString($value)
    {
        return $this->encrypt($value, false);
    }

    /**
     * Decrypt the given value.
     *
     * $allowedClasses goes to unserialize(): true for any class, false for
     * none (an object comes back as __PHP_Incomplete_Class and isn't woken),
     * or a list of class names. Pass false for data that holds no objects.
     *
     * @param  string  $payload
     * @param  bool  $unserialize
     * @param  bool|string[]  $allowedClasses
     * @return mixed
     *
     * @throws \WPSocialReviews\Framework\Encryption\DecryptException
     * @throws \InvalidArgumentException For $allowedClasses that isn't a bool or a list.
     */
    public function decrypt($payload, $unserialize = true, $allowedClasses = true)
    {
        if (!is_bool($allowedClasses) && !is_array($allowedClasses)) {
            throw new InvalidArgumentException('The allowed classes must be true, false or a list of class names.');
        }

        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv']);

        $this->ensureTagIsValid(
            $tag = empty($payload['tag']) ? null : base64_decode($payload['tag'])
        );

        $foundValidMac = false;

        // Try each key (current + rotated) in turn. For non-AEAD ciphers,
        // the MAC must be validated against THIS specific key — not a
        // previous iteration's. Once a key passes its MAC check, attempt
        // decryption with the same key.
        foreach ($this->getAllKeys() as $key) {
            if ($this->shouldValidateMac() && !$this->validMacForKey($payload, $key)) {
                continue;
            }

            $foundValidMac = true;

            $decrypted = \openssl_decrypt(
                $payload['value'], strtolower($this->cipher), $key, 0, $iv, $tag ?? ''
            );

            if ($decrypted !== false) {
                break;
            }
        }

        if ($this->shouldValidateMac() && !$foundValidMac) {
            throw new DecryptException('The MAC is invalid.');
        }

        if (($decrypted ?? false) === false) {
            throw new DecryptException('Could not decrypt the data.');
        }

        return $unserialize
            ? unserialize($decrypted, ['allowed_classes' => $allowedClasses])
            : $decrypted;
    }

    /**
     * Decrypt the given string without unserialization.
     *
     * @param  string  $payload
     * @return string
     *
     * @throws \WPSocialReviews\Framework\Encryption\DecryptException
     */
    public function decryptString($payload)
    {
        return $this->decrypt($payload, false);
    }

    /**
     * Create a MAC for the given value.
     *
     * @param  string  $iv
     * @param  mixed  $value
     * @param  string  $key
     * @return string
     */
    protected function hash($iv, $value, $key)
    {
        return hash_hmac('sha256', $iv.$value, $key);
    }

    /**
     * Get the JSON array from the given payload.
     *
     * @param  string  $payload
     * @return array
     *
     * @throws \WPSocialReviews\Framework\Encryption\DecryptException
     */
    protected function getJsonPayload($payload)
    {
        if (!is_string($payload)) {
            throw new DecryptException('The payload is invalid.');
        }

        $payload = json_decode(base64_decode($payload), true);

        // If the payload is not valid JSON or does not have the proper keys set we will
        // assume it is invalid and bail out of the routine since we will not be able
        // to decrypt the given value. We'll also check the MAC for this encryption.
        if (!$this->validPayload($payload)) {
            throw new DecryptException('The payload is invalid.');
        }

        return $payload;
    }

    /**
     * Verify that the encryption payload is valid.
     *
     * @param  mixed  $payload
     * @return bool
     */
    protected function validPayload($payload)
    {
        if (!is_array($payload)) {
            return false;
        }

        foreach (['iv', 'value', 'mac'] as $item) {
            if (!isset($payload[$item]) || !is_string($payload[$item])) {
                return false;
            }
        }

        if (isset($payload['tag']) && !is_string($payload['tag'])) {
            return false;
        }

        if (array_key_exists('v', $payload) && $payload['v'] !== static::PAYLOAD_VERSION) {
            throw new DecryptException(
                'The payload version is not one this encrypter reads: it may have been made by a newer one.'
            );
        }

        return strlen(base64_decode($payload['iv'], true)) === openssl_cipher_iv_length(strtolower($this->cipher));
    }

    /**
     * Determine if the MAC for the given payload is valid for the primary key.
     *
     * @param  array  $payload
     * @return bool
     */
    protected function validMac(array $payload)
    {
        return $this->validMacForKey($payload, $this->key);
    }

    /**
     * Determine if the MAC is valid for the given payload and key.
     *
     * @param  array  $payload
     * @param  string  $key
     * @return bool
     */
    protected function validMacForKey($payload, $key)
    {
        return hash_equals(
            $this->hash($payload['iv'], $payload['value'], $key), $payload['mac']
        );
    }

    /**
     * Ensure the given tag is a valid tag given the selected cipher.
     *
     * @param  string  $tag
     * @return void
     */
    protected function ensureTagIsValid($tag)
    {
        if (self::$supportedCiphers[strtolower($this->cipher)]['aead'] && strlen($tag) !== 16) {
            throw new DecryptException('Could not decrypt the data.');
        }

        if (!self::$supportedCiphers[strtolower($this->cipher)]['aead'] && is_string($tag)) {
            throw new DecryptException('Unable to use tag because the cipher algorithm does not support AEAD.');
        }
    }

    /**
     * Determine if we should validate the MAC while decrypting.
     *
     * @return bool
     */
    protected function shouldValidateMac()
    {
        return !self::$supportedCiphers[strtolower($this->cipher)]['aead'];
    }

    /**
     * Get the application encryption key.
     * 
     * Developers may override the database key name using the filter:
     * `{slug}.encryption.option_key`
     *
     * @return string
     */
    public function getSlug()
    {
        // Defensive: framework not bootstrapped (bare PHP, plugin
        // activation pre-init, early CLI). Fall back to a generic slug
        // so the constructor can still build a working encrypter.
        $app = App::getInstance();

        if (!$app) {
            return 'wpfluent_enc_key';
        }

        $slug = $app->config->get('app.slug');

        $default = $slug . '_enc_key';

        /**
         * Allow developer to override the encryption key option name.
         *
         * @param string $default Default option key name.
         */
        return $app->applyFilters($slug . '.encryption.option_key', $default);
    }

    /**
     * Get the encryption key that the encrypter is currently using.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * The key stored in the database, made and stored on first use.
     *
     * @return string
     */
    protected function getStoredKey()
    {
        if ($key = $this->readOption($this->slug)) {
            return base64_decode($key);
        }

        add_option($this->slug, base64_encode($this->generateKey($this->cipher)), '', false);

        // Another request may have stored one first: use what's stored.
        return base64_decode((string) $this->readOption($this->slug, true));
    }

    /**
     * An option's value. With $fresh, read from the database, so another
     * request's change and a stale option cache don't hide it.
     *
     * @param  string  $name
     * @param  bool  $fresh
     * @return mixed
     */
    protected function readOption($name, $fresh = false)
    {
        global $wpdb;

        $value = $fresh ? false : get_option($name);

        if ($value === false && isset($wpdb)) {
            $row = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $name
            ));

            $value = $row === null ? false : maybe_unserialize($row);
        }

        return $value;
    }

    /**
     * The stored old keys, as a list of strings.
     *
     * @param  bool  $fresh
     * @return string[]
     */
    protected function storedOldKeys($fresh = false)
    {
        $oldKeys = $this->readOption($this->oldKeysOptionName(), $fresh);

        if (!is_array($oldKeys)) {
            return [];
        }

        return array_values(array_filter($oldKeys, 'is_string'));
    }

    /**
     * Get the current encryption key and all previous encryption keys.
     *
     * This is useful for key rotation, allowing decryption of data
     * encrypted with older keys.
     *
     * @return array Array of binary encryption keys.
     */
    public function getAllKeys()
    {
        $keys = [$this->key];

        $encoded = $this->storedOldKeys();

        // With a key from a constant, data encrypted with the stored key from
        // before still decrypts.
        if ($this->keySource === 'constant' && ($stored = $this->readOption($this->slug))) {
            array_unshift($encoded, $stored);
        }

        // Data an earlier version encrypted with app.key still decrypts.
        if ($this->keySource !== 'given' && ($appKey = $this->appKey()) !== null) {
            $encoded[] = base64_encode($appKey);
        }

        foreach ($encoded as $encodedKey) {
            $decoded = base64_decode($encodedKey, true);

            // Skip corrupted base64 and any key whose length doesn't match
            // the active cipher (otherwise it'd silently fail in the
            // decrypt loop, hiding the real cause).
            if ($decoded === false || !static::supported($decoded, $this->cipher)) {
                continue;
            }

            if (!in_array($decoded, $keys, true)) {
                $keys[] = $decoded;
            }
        }

        return $keys;
    }

    /**
     * app.key, decoded, or null when it isn't set or isn't a valid key.
     *
     * @return string|null
     */
    protected function appKey()
    {
        $app = App::getInstance();

        $configured = $app ? $app->config->get('app.key') : null;

        if (!$configured) {
            return null;
        }

        try {
            $key = static::parseKey($configured);
        } catch (RuntimeException $e) {
            return null;
        }

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Resolve the option name that stores rotated old keys.
     * Honors the {slug}.encryption.old_keys_option filter when the
     * framework App is bootstrapped; otherwise uses the default.
     *
     * @return string
     */
    protected function oldKeysOptionName()
    {
        $default = $this->slug . '_old_enc_key';

        $app = App::getInstance();

        if (!$app) {
            return $default;
        }

        return $app->applyFilters(
            $this->slug . '.encryption.old_keys_option',
            $default
        );
    }

    /**
     * Rotate the encryption key.
     *
     * Archives the key in use and the stored key in the old keys list, then
     * stores a new key and uses it. Each save is checked: if one fails, it
     * throws and the key in use stays, so no data loses its key. One
     * rotation runs at a time.
     *
     * A key from a constant can't be rotated here: the next request would
     * read it from there again.
     *
     * @return void
     *
     * @throws \LogicException For a key from a constant.
     * @throws \RuntimeException When a rotation is already running or a save fails.
     */
    public function rotateKey()
    {
        if ($this->keySource === 'constant') {
            throw new LogicException('The encryption key comes from the ' . $this->keyConstant() . ' constant: change it there, and keep data encrypted with the old key decryptable first.');
        }

        $lock = $this->slug . '_rotating';

        $this->takeRotationLock($lock);

        try {
            $oldKeysOption = $this->oldKeysOptionName();

            $oldKeys = $this->storedOldKeys(true);

            // The key this encrypter uses, and the stored one: another
            // request may have rotated since this one read it.
            foreach ([base64_encode($this->key), $this->readOption($this->slug, true)] as $key) {
                if (is_string($key) && $key !== '' && !in_array($key, $oldKeys, true)) {
                    $oldKeys[] = $key;
                }
            }

            // Keep the most recent ones; trimmed-out keys mean any data still
            // encrypted under them becomes undecryptable. At least one stays.
            $cap = max(1, $this->oldKeysMax());

            if (count($oldKeys) > $cap) {
                $oldKeys = array_values(array_slice($oldKeys, -$cap));
            }

            if (!$this->saveOption($oldKeysOption, $oldKeys)) {
                throw new RuntimeException('Could not save the old keys: the encryption key was not rotated.');
            }

            $newKey = base64_encode(static::generateKey($this->cipher));

            if (!$this->saveOption($this->slug, $newKey)) {
                throw new RuntimeException('Could not save the new key: the encryption key was not rotated.');
            }

            $this->key = base64_decode($newKey);
            $this->keySource = 'database';
        } finally {
            delete_option($lock);
        }
    }

    /**
     * Take the rotation lock, or take over one left by a request that died.
     *
     * @param  string  $lock
     * @return void
     *
     * @throws \RuntimeException When a rotation is already running.
     */
    protected function takeRotationLock($lock)
    {
        if (add_option($lock, time(), '', false)) {
            return;
        }

        $since = (int) $this->readOption($lock, true);

        if ($since && time() - $since < static::ROTATION_LOCK_SECONDS) {
            throw new RuntimeException('A key rotation is already running.');
        }

        update_option($lock, time(), false);
    }

    /**
     * Save an option and check that the database has it.
     *
     * @param  string  $name
     * @param  mixed  $value
     * @return bool
     */
    protected function saveOption($name, $value)
    {
        update_option($name, $value, false);

        return $this->readOption($name, true) === $value;
    }

    /**
     * Maximum number of rotated keys to retain.
     *
     * Honors the {slug}.encryption.old_keys_max filter when the framework
     * App is bootstrapped. Default is 10, which covers ~10 weeks of weekly
     * rotation or ~10 years of annual rotation; bump the filter to keep
     * more historical keys around.
     *
     * @return int
     */
    protected function oldKeysMax()
    {
        $default = 10;

        $app = App::getInstance();

        if (!$app) {
            return $default;
        }

        return (int) $app->applyFilters(
            $this->slug . '.encryption.old_keys_max',
            $default
        );
    }
}

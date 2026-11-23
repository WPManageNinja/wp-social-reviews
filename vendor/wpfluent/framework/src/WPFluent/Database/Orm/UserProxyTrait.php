<?php

namespace WPSocialReviews\Framework\Database\Orm;

use WP_User;
use LogicException;
use BadMethodCallException;
use InvalidArgumentException;
use WPSocialReviews\Framework\Http\Request\WPUserProxy;

trait UserProxyTrait
{
    /**
     * Whether this model stands for a guest, which has no row to save.
     *
     * @var bool
     */
    protected $isGuestUser = false;

    /**
     * Resolve the WPUserProxy instance: WordPress's own object for the
     * current user, the user read by ID for anyone else.
     *
     * @return WPUserProxy
     */
    protected function resolveWPUser()
    {
        $userId = (int) $this->getKey();

        if (!$userId || (did_action('init') && $userId === get_current_user_id())) {
            return new WPUserProxy(wp_get_current_user());
        }

        return new WPUserProxy($userId);
    }

    /**
     * Make this model stand for a guest, so it refuses to save.
     *
     * @return $this
     */
    public function asGuest()
    {
        $this->isGuestUser = true;

        return $this;
    }

    /**
     * Save the user. When a stored user changed, clean WordPress's cache of
     * it so WordPress sees the change.
     *
     * @param array $options
     * @return bool
     * @throws LogicException For a guest's model.
     */
    public function save(array $options = [])
    {
        if ($this->isGuestUser) {
            throw new LogicException('A guest has no user to save.');
        }

        $stored = $this->exists;

        $saved = parent::save($options);

        if ($saved && $stored) {
            clean_user_cache((int) $this->getKey());
        }

        return $saved;
    }

    /**
     * Dual-purpose is() method:
     *  - is(string $role) - checks user role
     *  - is(User $model) - checks model identity
     *
     * @param string|self $value
     * @return bool
     */
    public function is($value)
    {
        if (is_string($value)) {
            return $this->resolveWPUser()->is($value);
        }

        if ($value instanceof static) {
            return parent::is($value);
        }

        return false;
    }

    /**
     * Dynamically call methods on the WPUserProxy if not found in the model.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     *
     * @throws BadMethodCallException|InvalidArgumentException
     */
    public function __call($method, $args)
    {
        try {
            return parent::__call($method, $args);
        } catch (BadMethodCallException $e) {
            try {
                return $this->resolveWPUser()->$method(...$args);
            } catch (BadMethodCallException $proxyError) {
                throw $proxyError;
            } catch (InvalidArgumentException $invalidUser) {
                throw new BadMethodCallException(
                    "Call to undefined method " . static::class . "::{$method}()",
                    0,
                    $invalidUser
                );
            }
        }
    }
}

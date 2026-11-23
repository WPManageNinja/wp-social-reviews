<?php

namespace WPSocialReviews\Framework\Http\Request;

use WP_User;
use ReflectionMethod;
use JsonSerializable;
use WPSocialReviews\Framework\Support\Str;
use WPSocialReviews\Framework\Database\Orm\Model;
use WPSocialReviews\Framework\Database\Orm\UserProxyTrait;

/**
 * WordPress's current user with the same behaviour, and a way through to the
 * plugin's User model. It stores no user data: every read asks WordPress, so
 * a held object always means whoever is logged in now.
 *
 * A name WordPress knows is answered by WordPress. Otherwise the plugin's
 * User class answers, for what that class adds: its relations, accessors and
 * methods. Everything else on the model goes through model().
 */
class CurrentUser extends WPUserProxy implements JsonSerializable
{
    /**
     * Whether the WordPress hooks are registered.
     *
     * @var bool
     */
    protected static $listening = false;

    /**
     * The plugin's User class, null when it has no usable one.
     *
     * @var class-string|null
     */
    protected $modelClass = null;

    /**
     * The model of the user it was last asked for.
     *
     * @var \WPSocialReviews\Framework\Database\Orm\Model|null
     */
    protected $model = null;

    /**
     * The ID of the user the model belongs to, 0 for a blank one.
     *
     * @var int
     */
    protected $modelUserId = 0;

    /**
     * Use a User class only when it has UserProxyTrait.
     *
     * @param class-string|null $modelClass
     */
    public function __construct($modelClass = null)
    {
        if ($modelClass && class_exists($modelClass) && method_exists($modelClass, 'asGuest')) {
            $this->modelClass = $modelClass;
        }
    }

    /**
     * Keep WordPress's current user object up to date when WordPress says a
     * user's row or saved capabilities changed. Nothing changes when the user
     * isn't the current one, or WordPress hasn't loaded a current user yet.
     *
     * @return void
     */
    public static function listenToWordPress()
    {
        if (static::$listening) {
            return;
        }

        static::$listening = true;

        add_action('clean_user_cache', [static::class, 'refreshRow']);

        foreach (['added', 'updated', 'deleted'] as $action) {
            add_action("{$action}_user_meta", [static::class, 'refreshCapabilities'], 10, 3);
        }
    }

    /**
     * Copy the saved columns onto WordPress's current user object. Anything
     * else set on it in memory stays.
     *
     * @param int $userId
     * @return void
     */
    public static function refreshRow($userId)
    {
        global $current_user;

        if (!$current_user instanceof WP_User || (int) $current_user->ID !== (int) $userId) {
            return;
        }

        if ($row = WP_User::get_data_by('id', $userId)) {
            foreach ((array) $row as $column => $value) {
                $current_user->data->{$column} = $value;
            }
        }
    }

    /**
     * Load the saved capabilities again on WordPress's current user object.
     *
     * @param int|array $metaId
     * @param int $userId
     * @param string $metaKey
     * @return void
     */
    public static function refreshCapabilities($metaId, $userId, $metaKey)
    {
        global $current_user;

        if (
            $current_user instanceof WP_User
            && (int) $current_user->ID === (int) $userId
            && $metaKey === $current_user->cap_key
        ) {
            $current_user->for_site($current_user->get_site_id());
        }
    }

    /**
     * The plugin's User model for the current user, with the saved columns
     * and any unsaved edits. A blank model for a guest, which refuses to save.
     * Null when the plugin has no User class with UserProxyTrait.
     *
     * @return \WPSocialReviews\Framework\Database\Orm\Model|null
     */
    public function model()
    {
        if (!$this->modelClass) {
            return null;
        }

        $userId = (int) $this->wpUser()->ID;
        $row = $userId ? WP_User::get_data_by('id', $userId) : false;

        if (!$row) {
            $userId = 0;
        }

        if (!$this->model || $this->modelUserId !== $userId) {
            $this->model = $this->newModel($row);
            $this->modelUserId = $userId;
        }

        if ($row) {
            $this->fillModel($row);
        }

        return $this->model;
    }

    /**
     * What a relation stores for this user: the model, the ID when the plugin
     * has no User model, and nothing for a guest.
     *
     * @return \WPSocialReviews\Framework\Database\Orm\Model|int|null
     */
    public function forRelation()
    {
        return $this->exists() ? ($this->model() ?? $this->id()) : null;
    }

    /**
     * The user as an array, without the password hash or the reset key, even
     * when the plugin's User model doesn't hide them.
     *
     * @return array
     */
    public function toArray()
    {
        if (!$this->exists()) {
            return [];
        }

        $model = $this->model();

        return $model
            ? array_diff_key($model->toArray(), array_flip(['user_pass', 'user_activation_key']))
            : parent::toArray();
    }

    /**
     * The user for JSON: the same as toArray().
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Read a property from WordPress, else from what the User class adds.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        $wpUser = $this->wpUser();

        if (!isset($wpUser->{$key}) && $this->modelAnswers($key)) {
            return $this->model()->{$key};
        }

        return $wpUser->{$key};
    }

    /**
     * Whether WordPress, or what the User class adds, has a value for the name.
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->wpUser()->{$key})
            || ($this->modelAnswers($key) && isset($this->model()->{$key}));
    }

    /**
     * Call a WP_User method, else a method the User class adds.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, $args)
    {
        if (!$this->isWordPressMethod($method) && $this->isAddedByTheModel($method)) {
            return $this->model()->{$method}(...$args);
        }

        return parent::__call($method, $args);
    }

    /**
     * WordPress's current user object. There's no user before init, when
     * WordPress hasn't worked it out yet.
     *
     * @return \WP_User
     */
    protected function wpUser()
    {
        return did_action('init') ? wp_get_current_user() : new WP_User(0);
    }

    /**
     * The model as a query would give it for the row, or a blank one that
     * refuses to save.
     *
     * @param object|false $row
     * @return \WPSocialReviews\Framework\Database\Orm\Model
     */
    protected function newModel($row)
    {
        $model = new $this->modelClass;

        return $row
            ? $model->newFromBuilder((array) $row, $model->getConnection()->getName())
            : $model->asGuest();
    }

    /**
     * Put the saved row on the model, keeping its unsaved edits.
     *
     * @param object $row
     * @return void
     */
    protected function fillModel($row)
    {
        $edits = $this->model->getDirty();

        $this->model->setRawAttributes((array) $row, true);
        $this->model->exists = true;

        if ($edits) {
            $this->model->setRawAttributes(array_merge((array) $row, $edits));
        }
    }

    /**
     * Whether the name is an accessor, attribute or relation of the User class.
     *
     * @param string $key
     * @return bool
     */
    protected function modelAnswers($key)
    {
        if (!$model = $this->model()) {
            return false;
        }

        return $model->hasAttribute($key)
            || $model->relationLoaded($key)
            || ($model->isRelation($key) && $this->isAddedByTheModel($key));
    }

    /**
     * Whether WP_User or the proxy has the method.
     *
     * @param string $method
     * @return bool
     */
    protected function isWordPressMethod($method)
    {
        return array_key_exists($method, $this->passthrough)
            || method_exists(WP_User::class, $method)
            || method_exists(WP_User::class, Str::snake($method));
    }

    /**
     * Whether the method is public and comes from the plugin's User class,
     * not from the framework's model or UserProxyTrait.
     *
     * @param string $method
     * @return bool
     */
    protected function isAddedByTheModel($method)
    {
        return $this->modelClass
            && method_exists($this->modelClass, $method)
            && !method_exists(Model::class, $method)
            && !method_exists(UserProxyTrait::class, $method)
            && (new ReflectionMethod($this->modelClass, $method))->isPublic();
    }
}

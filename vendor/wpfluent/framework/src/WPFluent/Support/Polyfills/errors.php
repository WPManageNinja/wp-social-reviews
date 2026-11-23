<?php

if (!class_exists('ValueError', false)) {
    /**
     * PHP 8's ValueError, for PHP 7.4: thrown when an argument has the
     * right type but a value the function cannot take. The definition is
     * PHP's own (a subclass of Error, not final, nothing added) and the one
     * symfony/polyfill-php80 ships, so whichever loads first, the class is
     * the same. The framework throws it on every PHP version where PHP 8
     * would, with PHP's latest message.
     */
    class ValueError extends Error
    {
    }
}

<?php

namespace WPSocialReviews\Framework\Support\Facades;

use WPSocialReviews\Framework\Support\Facade;

/**
 * Static calls that start a new email: every call gets a fresh Mail, so
 * one email's recipients never carry over to the next.
 *
 * @method static \WPSocialReviews\Framework\Support\Mail to(string|array $emails)
 * @method static \WPSocialReviews\Framework\Support\Mail from(string $email, string|null $name = null)
 * @method static \WPSocialReviews\Framework\Support\Mail cc(string|array $emails)
 * @method static \WPSocialReviews\Framework\Support\Mail bcc(string|array $emails)
 * @method static \WPSocialReviews\Framework\Support\Mail subject(string $subject)
 * @method static \WPSocialReviews\Framework\Support\Mail body(string $body)
 * @method static \WPSocialReviews\Framework\Support\Mail view(string $templatePath, array $data = [])
 *
 * @see \WPSocialReviews\Framework\Support\Mail
 */
class Mail extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'mail';
    }
}

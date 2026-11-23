<?php

namespace WPSocialReviews\Framework\Foundation\Exceptions;

use Throwable;

/**
 * An exception that says how it is answered: its status, a stable error
 * code, extra data and headers. Its message is shown to the client.
 *
 * `HttpException` implements it, and extending that class is the usual
 * way. Implement the interface on an exception that already has a parent,
 * such as one that extends a vendor SDK's exception.
 */
interface HttpExceptionInterface extends Throwable
{
    /**
     * The HTTP status of the response.
     *
     * @return int
     */
    public function getStatusCode();

    /**
     * A machine-readable error code for the response body.
     *
     * @return string
     */
    public function getErrorCode();

    /**
     * Extra data for the response body.
     *
     * @return array
     */
    public function getData();

    /**
     * Headers for the response, such as Retry-After.
     *
     * @return array
     */
    public function getHeaders();
}

<?php

namespace WPSocialReviews\Framework\Support\Exceptions;

use RuntimeException;

class ItemNotFoundException extends RuntimeException
{
	protected $message = 'No item was found in the collection.';
}

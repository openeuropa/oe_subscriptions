<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Exception;

/**
 * An exception thrown when an e-mail belongs to registered coupled user.
 */
class RegisteredUserEmailException extends \InvalidArgumentException {}

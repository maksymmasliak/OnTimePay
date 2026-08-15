<?php

namespace App\Exceptions;

/**
 * Marks a webhook-processing failure as permanent (bad/tampered metadata,
 * unknown invoice) — retrying delivery from Stripe won't fix it.
 *
 * Deliberately extends \Exception, not \RuntimeException: Laravel's
 * QueryException (and PHP's own PDOException) also extend RuntimeException,
 * so catching that broad type in the controller would wrongly swallow real,
 * transient database failures as "permanent" and stop Stripe from retrying
 * exactly when a retry would actually help.
 */
class WebhookPermanentException extends \Exception
{
}

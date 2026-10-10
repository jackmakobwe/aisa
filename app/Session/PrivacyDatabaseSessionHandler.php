<?php

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * The standard database session handler records the IP address and user
 * agent of every session. For anonymous visitors that is unnecessary
 * personal data, so this handler stores neither.
 */
class PrivacyDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function addRequestInformation(&$payload)
    {
        return $this;
    }
}

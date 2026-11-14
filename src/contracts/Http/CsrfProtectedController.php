<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Http;

/**
 * A controller of the admin whose requests that change something (POST, PUT, PATCH, DELETE) must carry the CSRF token of the
 * admin, in the X-CSRF-Token header, as the admin's own scripts send it. The bundle of the AI OS refuses the others before the
 * action runs: a controller only says that it wants it, as Ibexa's RestrictedControllerInterface says that it wants an access check.
 */
interface CsrfProtectedController
{
}

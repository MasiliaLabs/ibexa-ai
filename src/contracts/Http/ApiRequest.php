<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Http;

use Symfony\Component\HttpFoundation\Request;

/** What an endpoint of the admin API reads from the request: its JSON body, the siteaccess it is served by, the user. */
interface ApiRequest
{
    /**
     * The JSON object of the body, or null when the body is not JSON or not an object.
     *
     * @return array<string, mixed>|null
     */
    public function json(Request $request): ?array;

    /** The name of the siteaccess the request is served by. */
    public function siteaccess(): string;

    public function userId(): int;
}

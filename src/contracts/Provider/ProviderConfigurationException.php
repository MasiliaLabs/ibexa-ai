<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Provider;

use RuntimeException;

/** No AI provider can be used for the siteaccess: none is configured, it is not supported, or its key cannot be read. */
final class ProviderConfigurationException extends RuntimeException
{
}

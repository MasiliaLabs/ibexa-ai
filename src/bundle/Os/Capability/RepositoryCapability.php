<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability;

use Ibexa\Contracts\Core\Repository\Repository;

/**
 * A capability written directly on the repository services.
 */
abstract class RepositoryCapability extends BaseCapability
{
    public function __construct(protected readonly Repository $repository)
    {
    }

    /** The language the names and descriptions of structures are written in: the installation's default. */
    protected function defaultLanguage(): string
    {
        return $this->repository->getContentLanguageService()->getDefaultLanguageCode();
    }
}

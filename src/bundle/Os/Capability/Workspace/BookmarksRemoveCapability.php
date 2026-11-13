<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Workspace;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class BookmarksRemoveCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'workspace.bookmarks.remove';
    }

    public function summary(): string
    {
        return 'Remove a location from the bookmarks of the user.';
    }

    public function description(): string
    {
        return 'Removes the bookmark only: the location and its content are untouched.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['location_id'], 'properties' => ['location_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'read']];
    }

    public function examples(): array
    {
        return [['description' => 'Remove a bookmark', 'arguments' => ['location_id' => 1364]]];
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Remove a bookmark', [['Location', (string) (int) $arguments['location_id']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $locations = $this->repository->getLocationService();
        $this->repository->getBookmarkService()->deleteBookmark($locations->loadLocation((int) $arguments['location_id']));

        return CapabilityResult::ok('The bookmark was removed', []);
    }
}

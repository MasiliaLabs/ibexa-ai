<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use Ibexa\Contracts\Core\Repository\ObjectStateService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\RoleService;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectState;
use Ibexa\Contracts\Core\Repository\Values\ObjectState\ObjectStateGroup;
use Ibexa\Contracts\Core\Repository\Values\User\Role;
use Masilia\IbexaAi\Ibexa\Content\Trash\TrashRestorer;
use Masilia\IbexaAi\Os\Capability\Access\AssignmentAddCapability;
use Masilia\IbexaAi\Os\Capability\State\DeleteCapability;
use Masilia\IbexaAi\Os\Capability\Trash\RestoreCapability;
use PHPUnit\Framework\TestCase;

/** Rules of the Ibexa admin that the assistant follows too, each named by the screen it comes from. */
final class AdminRulesTest extends TestCase
{
    public function testAStateThatHoldsContentsIsNotDeleted(): void
    {
        // Admin: the list of states (ObjectStateController) only offers to delete a state no content is in.
        $states = $this->createMock(ObjectStateService::class);
        $states->method('loadObjectStateGroupByIdentifier')->willReturn($this->createMock(ObjectStateGroup::class));
        $states->method('loadObjectStateByIdentifier')->willReturn($this->createMock(ObjectState::class));
        $states->method('getContentCount')->willReturnOnConsecutiveCalls(3, 0);
        $repository = $this->createMock(Repository::class);
        $repository->method('getObjectStateService')->willReturn($states);
        $capability = new DeleteCapability($repository);

        self::assertStringContainsString('still holds 3 content(s)', (string) $capability->validate(['group' => 'review', 'state' => 'done']));
        self::assertNull($capability->validate(['group' => 'review', 'state' => 'done']));
    }

    public function testAContentCanBeRestoredBelowAContainerOnly(): void
    {
        // Admin: trash restore with a new parent (TrashController::restoreAction: TrashService::recover($item, $newParent)).
        $container = $this->parent(true);
        $page = $this->parent(false);
        $restorer = $this->createMock(TrashRestorer::class);
        $restorer->method('parentLocation')->willReturnCallback(static fn (int $id): Location => match ($id) {
            20 => $container,
            55 => $page,
            default => throw new \RuntimeException('no location'),
        });
        $capability = new RestoreCapability($restorer);

        self::assertNull($capability->validate(['content_ids' => [1], 'parent_location_id' => 20]));
        self::assertNull($capability->validate(['content_ids' => [1]]));
        self::assertSame('Contents can only be restored below a container.', $capability->validate(['content_ids' => [1], 'parent_location_id' => 55]));
        self::assertSame('The location 9 does not exist.', $capability->validate(['content_ids' => [1], 'parent_location_id' => 9]));
    }

    public function testTheNewParentIsPassedToTheRestore(): void
    {
        $restorer = $this->createMock(TrashRestorer::class);
        $restorer->expects(self::once())->method('restore')->with([1353], 20)->willReturn([1353]);

        self::assertSame(1, (new RestoreCapability($restorer))->execute(['content_ids' => [1353], 'parent_location_id' => 20])->data['count']);
    }

    public function testALimitationOfARoleAssignmentNeedsItsValues(): void
    {
        // Admin: RoleAssignmentCreateData: "Define a Subtree or Section limitation".
        $roles = $this->createMock(RoleService::class);
        $roles->method('loadRoleByIdentifier')->willReturn($this->createMock(Role::class));
        $roles->method('getLimitationType')->willReturn($this->createMock(\Ibexa\Contracts\Core\Limitation\Type::class));
        $users = $this->createMock(\Ibexa\Contracts\Core\Repository\UserService::class);
        $repository = $this->createMock(Repository::class);
        $repository->method('getRoleService')->willReturn($roles);
        $repository->method('getUserService')->willReturn($users);
        $capability = new AssignmentAddCapability($repository, []);

        self::assertSame(
            'Define a subtree or section limitation: the limitation needs its values.',
            $capability->validate(['role' => 'Editor', 'user_group_id' => 12, 'limitation' => ['type' => 'Section', 'values' => []]]),
        );
        self::assertNull($capability->validate(['role' => 'Editor', 'user_group_id' => 12, 'limitation' => ['type' => 'Section', 'values' => [1]]]));
    }

    private function parent(bool $container): Location
    {
        $type = $this->createMock(ContentType::class);
        $type->method('isContainer')->willReturn($container);
        $info = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo::class);
        $info->method('getContentType')->willReturn($type);
        $location = $this->createMock(Location::class);
        $location->method('getContentInfo')->willReturn($info);

        return $location;
    }
}

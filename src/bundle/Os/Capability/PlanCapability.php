<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability;

use InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Agent\Worker\Plan;
use Masilia\Bundle\IbexaAi\Agent\Worker\PlanBuilder;
use Masilia\Bundle\IbexaAi\Agent\Worker\PlanExecutor;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;
use Throwable;

/**
 * A capability that does what a plan intent does. It goes through the plan builder and executor the assistant already
 * had, so that it keeps all their checks (the content type exists, the parent is in the right site, the language is one
 * of the site's, the image is the user's) and their way of writing.
 */
abstract class PlanCapability extends BaseCapability
{
    public function __construct(
        private readonly PlanBuilder $builder,
        private readonly PlanExecutor $executor,
        private readonly Repository $repository,
    ) {
    }

    /**
     * The arguments of the plan this capability makes of its own arguments (see Plan::fromArray).
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    abstract protected function plan(array $arguments): array;

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->builder->build($this->plan($arguments));
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return PlanPreview::of($this->builder->build($this->plan($arguments)), function (int $id): string {
            try {
                return (string) $this->repository->getContentService()->loadContentInfo($id)->getName();
            } catch (Throwable) {
                return '';
            }
        });
    }

    public function execute(array $arguments): CapabilityResult
    {
        try {
            $plan = $this->builder->build($this->plan($arguments));
        } catch (InvalidArgumentException $e) {
            return CapabilityResult::error($e->getMessage());
        }

        $result = $this->executor->execute($plan);
        if (!$result->success) {
            return CapabilityResult::error($result->message);
        }

        return CapabilityResult::ok($result->message, array_filter(
            ['content_id' => $result->contentId, 'location_id' => $result->locationId] + $result->data,
            static fn (mixed $value): bool => $value !== null,
        ));
    }
}

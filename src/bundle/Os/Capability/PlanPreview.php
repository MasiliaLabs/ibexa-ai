<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability;

use Masilia\Bundle\IbexaAi\Agent\Worker\Plan;
use Masilia\Contracts\IbexaAi\Os\Preview;

/**
 * What a plan will do, as the rows of an approval card.
 */
final class PlanPreview
{
    private const TITLES = [
        Plan::INTENT_CREATE_CONTENT => 'Create a content',
        Plan::INTENT_CREATE_FOLDER => 'Create a folder',
        Plan::INTENT_ADD_IMAGE => 'Add an image to the media library',
        Plan::INTENT_TRASH_CONTENT => 'Move to the trash',
        Plan::INTENT_RESTORE_CONTENT => 'Restore from the trash',
        Plan::INTENT_UPDATE_CONTENT => 'Update content',
    ];

    /**
     * @param (callable(int): string)|null $nameOf The name of a content, when it can be read
     */
    public static function of(Plan $plan, ?callable $nameOf = null): Preview
    {
        $rows = [];
        if ($plan->title !== null && $plan->title !== '') {
            $rows[] = ['Title', $plan->title];
        }
        if ($plan->contentType !== null && $plan->contentType !== '') {
            $rows[] = ['Content type', $plan->contentType];
        }
        if ($plan->contentId !== null) {
            $name = $nameOf !== null ? $nameOf($plan->contentId) : '';
            $rows[] = ['Content', $name !== '' ? sprintf('%s (%d)', $name, $plan->contentId) : (string) $plan->contentId];
        }
        $restored = $plan->fields['content_ids'] ?? null;
        if (is_array($restored) && $restored !== []) {
            $rows[] = ['Contents', implode(', ', array_map('strval', $restored))];
        }
        if ($plan->parentLocationId !== null) {
            $rows[] = ['Parent location', (string) $plan->parentLocationId];
        }
        if ($plan->siteaccess !== null && $plan->siteaccess !== '') {
            $rows[] = ['Site', $plan->siteaccess];
        }
        if ($plan->language !== null) {
            $rows[] = ['Language', $plan->language];
        }
        if ($plan->attachmentId !== null) {
            $rows[] = ['Image', 'attached by you'];
        }
        if ($plan->intent === Plan::INTENT_CREATE_CONTENT && $plan->fields !== []) {
            $rows[] = ['Fields', implode(', ', array_map('strval', array_slice(array_keys($plan->fields), 0, 14)))];
        }

        $notes = $plan->intent === Plan::INTENT_ADD_IMAGE && $plan->description !== null && $plan->description !== '' ? [sprintf('Alternative text: %s', $plan->description)] : [];

        return new Preview(self::TITLES[$plan->intent] ?? $plan->intent, $rows, $notes);
    }
}

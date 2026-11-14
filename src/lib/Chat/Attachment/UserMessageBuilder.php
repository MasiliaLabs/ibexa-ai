<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\Attachment;

use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\UserMessage;

/**
 * The user's message for the agent. The text keeps the tags of the images it names, so that the conversation shows
 * them and the agent can refer to them. When the model reads images, the images the user owns go with the message.
 */
readonly class UserMessageBuilder
{
    public function __construct(private AttachmentStore $attachments)
    {
    }

    public function build(string $text, bool $vision, int $userId): UserMessage
    {
        if (!$vision) {
            return new UserMessage($text);
        }

        $blocks = [new TextContent($text)];
        foreach (ImageTags::ids($text) as $id) {
            // Only the user's own attachment is found: another user's id is an image that is not there.
            $attachment = $this->attachments->find($id, $userId);
            $image = $attachment === null ? null : ImageForModel::prepare($this->attachments->path($attachment), $attachment->mime, $attachment->width, $attachment->height);
            if ($image !== null) {
                $blocks[] = new ImageContent(base64_encode($image['data']), SourceType::BASE64, $image['mime']);
            }
        }

        return count($blocks) === 1 ? new UserMessage($text) : new UserMessage($blocks);
    }
}

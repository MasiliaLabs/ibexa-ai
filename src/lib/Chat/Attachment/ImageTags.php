<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\Attachment;

/**
 * An image attached to a message is named in its text by a tag the chat writes when the message is sent:
 *
 *   <attachment-image name="photo.jpg" id="3f2b…" width="1200" height="800" bytes="123456"/>
 *
 * The id is what the server looks up (for its owner only); the rest is for whoever reads the message.
 */
final class ImageTags
{
    public const PATTERN = '/<attachment-image name="([^"]*)" id="([0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})" width="\d+" height="\d+" bytes="\d+"\/>/';

    /** Most images that go to the model with one message. */
    public const MAX = 3;

    /**
     * @return list<string> The ids, in order, without repeats
     */
    public static function ids(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_slice(array_values(array_unique($matches[2])), 0, self::MAX);
    }
}

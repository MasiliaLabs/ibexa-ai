<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Content;

/** The languages contents can be written in: the enabled languages of the installation. */
interface Languages
{
    /**
     * The code of the enabled language a request names: an exact code ("fre-FR"), or a language whatever its region ("fr",
     * "fre"). Null when no enabled language matches.
     */
    public function resolve(string $requested): ?string;

    /** The language of the installation a content is written in when nothing says otherwise. */
    public function default(): string;
}

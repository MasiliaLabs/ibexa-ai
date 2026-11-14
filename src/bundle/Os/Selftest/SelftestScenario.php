<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Selftest;

// The name this type had until masilia/ibexa-ai 0.5. It is \Masilia\Contracts\IbexaAi\Os\Selftest\SelftestScenario since 0.6 (the public API of the
// package is in Masilia\Contracts\IbexaAi), and this name goes in 1.0.
@trigger_error(sprintf('%s is deprecated since masilia/ibexa-ai 0.6, use %s instead.', SelftestScenario::class, \Masilia\Contracts\IbexaAi\Os\Selftest\SelftestScenario::class), \E_USER_DEPRECATED);

class_alias(\Masilia\Contracts\IbexaAi\Os\Selftest\SelftestScenario::class, SelftestScenario::class);

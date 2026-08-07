<?php

namespace Apps\Hulahoot\Service;

/**
 * Class HulahootPublisher
 *
 * The seam where a Partner Portal promotion will eventually get pushed
 * to the main Hulahoot application - deliberately not implemented yet.
 * Phase 2's job is only to make sure the rest of the codebase (the
 * promotion composer, the entitlement check, everything upstream of
 * "send this promotion out") is already written to call through here,
 * so wiring up the real HTTP client later is a one-file change instead
 * of a redesign. See docs/HULAHOOT_INTEGRATION.md for the intended
 * request/response shape once the main application's API is defined.
 *
 * Every method throws \RuntimeException unconditionally right now -
 * there is no silent fallback, no fake success response. Anything that
 * calls this class should let that exception surface as "publishing
 * isn't available yet," not swallow it.
 *
 * @package Apps\Hulahoot\Service
 */
class HulahootPublisher
{
    /**
     * Publish one promotion to the main Hulahoot application.
     *
     * @param array $aPromotionData shape to be finalized alongside the
     *        promotion composer itself (Phase 3) - expected to include
     *        at minimum the owning user, the Industry, and whatever
     *        content/media the promotion carries.
     *
     * @return never
     *
     * @throws \RuntimeException always, until Phase 3 implements this
     */
    public function publish(array $aPromotionData)
    {
        throw new \RuntimeException(
            'Publishing to the main Hulahoot application is not implemented yet. '
            . 'See docs/HULAHOOT_INTEGRATION.md.'
        );
    }
}

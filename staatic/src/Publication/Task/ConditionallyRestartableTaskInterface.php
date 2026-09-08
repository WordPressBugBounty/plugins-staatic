<?php

declare(strict_types=1);

namespace Staatic\WordPress\Publication\Task;

use Staatic\WordPress\Publication\Publication;

/**
 * A task whose ability to pick up where it left off depends on the publication it is running
 * for, rather than on the task itself.
 *
 * RestartableTaskInterface is a promise the class makes once, for every publication. That is
 * wrong for a task that delegates the work to something chosen at runtime: initiating a
 * deployment can resume against Staatic Cloud and cannot resume against Netlify, and the
 * difference is a setting, not a class. Declaring the promise unconditionally would drop the
 * crash-loop guard for every deployment method that still needs it.
 *
 * A task implementing this interface is asked per publication. It must answer false whenever it
 * cannot tell — the guard failing a publication that could have resumed costs a retry, while the
 * guard being skipped where it was needed lets a fatal re-enter and create a second remote
 * deployment.
 */
interface ConditionallyRestartableTaskInterface extends TaskInterface
{
    /**
     * Whether reaching this task a second time for the given publication is a resumption rather
     * than the aftermath of an attempt that ended unexpectedly.
     * @param Publication $publication
     */
    public function isRestartable($publication): bool;
}

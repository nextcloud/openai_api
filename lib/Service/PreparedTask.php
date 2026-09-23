<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

use OCP\TaskProcessing\Task;

/**
 * A task ready to be scheduled, together with the files staged for it.
 */
class PreparedTask {
	/**
	 * @param list<int> $stagedFileIds
	 */
	public function __construct(
		public readonly Task $task,
		public readonly string $taskTypeId,
		public readonly array $stagedFileIds,
	) {
	}
}

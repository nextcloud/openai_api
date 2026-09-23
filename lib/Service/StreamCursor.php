<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

/**
 * Turns the cumulative snapshots a task exposes while running into the deltas
 * an OpenAI stream is made of.
 *
 * Providers report intermediate output cumulatively - each report carries the
 * full text produced so far - so a delta is the part of the snapshot that has
 * not been emitted yet. A snapshot that is not an extension of what was emitted
 * is treated as fresh text rather than dropped, so a provider that reports
 * deltas instead still produces a readable stream.
 */
class StreamCursor {
	private string $emitted = '';

	public function advance(string $snapshot): string {
		if ($snapshot === $this->emitted) {
			return '';
		}
		if (str_starts_with($snapshot, $this->emitted)) {
			$delta = substr($snapshot, strlen($this->emitted));
			$this->emitted = $snapshot;
			return $delta;
		}
		$this->emitted = $snapshot;
		return $snapshot;
	}

	public function getEmitted(): string {
		return $this->emitted;
	}
}

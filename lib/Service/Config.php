<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

use OCA\OpenAiApi\AppInfo\Application;
use OCA\OpenAiApi\ConfigLexicon;
use OCP\IAppConfig;

class Config {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * Whether "stream": true should emit provider output as it is produced.
	 * When false, streaming requests are answered with a single content chunk.
	 */
	public function isRealStreamingEnabled(): bool {
		return $this->appConfig->getValueBool(
			Application::APP_ID,
			ConfigLexicon::REAL_STREAMING,
			ConfigLexicon::REAL_STREAMING_DEFAULT,
		);
	}

	/**
	 * Seconds to wait for a task before cancelling it.
	 */
	public function getRequestTimeout(): int {
		return max(1, $this->appConfig->getValueInt(
			Application::APP_ID,
			ConfigLexicon::REQUEST_TIMEOUT,
			ConfigLexicon::REQUEST_TIMEOUT_DEFAULT,
		));
	}

	/**
	 * Milliseconds between polls of a running task.
	 */
	public function getPollIntervalMs(): int {
		return max(50, $this->appConfig->getValueInt(
			Application::APP_ID,
			ConfigLexicon::POLL_INTERVAL_MS,
			ConfigLexicon::POLL_INTERVAL_MS_DEFAULT,
		));
	}

	/**
	 * Seconds after which staged input files are considered abandoned.
	 */
	public function getStagedFileTtl(): int {
		return max(60, $this->appConfig->getValueInt(
			Application::APP_ID,
			ConfigLexicon::STAGED_FILE_TTL,
			ConfigLexicon::STAGED_FILE_TTL_DEFAULT,
		));
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

/**
 * Writes server-sent events and reports when the client has gone away.
 */
class SseWriter {
	/**
	 * Send one `data:` event carrying a JSON payload.
	 */
	public function event(array $payload): void {
		$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($json === false) {
			$json = '{}';
		}
		$this->write('data: ' . $json . "\n\n");
	}

	/**
	 * Send the terminating `data: [DONE]` sentinel.
	 */
	public function done(): void {
		$this->write("data: [DONE]\n\n");
	}

	/**
	 * Send a comment. Keeps the connection warm and makes PHP notice a client
	 * that has disconnected without any payload reaching the client.
	 */
	public function comment(string $text): void {
		$this->write(': ' . $text . "\n\n");
	}

	/**
	 * Whether the client has closed the connection.
	 */
	public function isAborted(): bool {
		return connection_aborted() !== 0;
	}

	private function write(string $data): void {
		echo $data;
		// The response may sit inside one or more output buffers; flush all of them
		// so the chunk actually reaches the client instead of the buffer.
		while (ob_get_level() > 0) {
			@ob_end_flush();
		}
		@flush();
	}
}

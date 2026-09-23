<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Response;

use OCA\OpenAiApi\Service\SseWriter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\Response;

/**
 * A text/event-stream response whose body is produced by a callback.
 *
 * @template-extends Response<Http::STATUS_OK, array<string, string>>
 */
class SseResponse extends Response implements ICallbackResponse {
	/**
	 * @param \Closure(SseWriter):void $producer
	 */
	public function __construct(
		private readonly \Closure $producer,
	) {
		parent::__construct(Http::STATUS_OK);

		$this->addHeader('Content-Type', 'text/event-stream; charset=utf-8');
		$this->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
		$this->addHeader('Connection', 'keep-alive');
		// Tell nginx and other buffering reverse proxies to pass chunks straight through
		$this->addHeader('X-Accel-Buffering', 'no');
	}

	#[\Override]
	public function callback(IOutput $output): void {
		// The stream is torn down by the writer when the client goes away, so the
		// request must survive the abort long enough to cancel the running task.
		ignore_user_abort(true);
		set_time_limit(0);

		($this->producer)(new SseWriter());
	}
}

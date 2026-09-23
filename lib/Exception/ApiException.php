<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Exception;

use OCP\AppFramework\Http;

/**
 * An error that is reported to the client in the OpenAI error envelope.
 */
class ApiException extends \Exception {
	public function __construct(
		string $message,
		private readonly int $status = Http::STATUS_BAD_REQUEST,
		private readonly string $type = 'invalid_request_error',
		private readonly ?string $param = null,
		private readonly ?string $errorCode = null,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}

	public function getStatus(): int {
		return $this->status;
	}

	/**
	 * @return array{error: array{message: string, type: string, param: ?string, code: ?string}}
	 */
	public function toArray(): array {
		return [
			'error' => [
				'message' => $this->getMessage(),
				'type' => $this->type,
				'param' => $this->param,
				'code' => $this->errorCode,
			],
		];
	}

	public static function invalidRequest(string $message, ?string $param = null): self {
		return new self($message, Http::STATUS_BAD_REQUEST, 'invalid_request_error', $param);
	}

	public static function modelNotFound(string $model): self {
		return new self(
			'The model "' . $model . '" does not exist or is not available to you.',
			Http::STATUS_NOT_FOUND,
			'invalid_request_error',
			'model',
			'model_not_found',
		);
	}

	public static function serverError(string $message, ?\Throwable $previous = null): self {
		return new self($message, Http::STATUS_INTERNAL_SERVER_ERROR, 'server_error', null, null, $previous);
	}
}

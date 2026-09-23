<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Response;

use OCA\OpenAiApi\Exception\ApiException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

/**
 * @psalm-type ApiErrorStatus = Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_SERVICE_UNAVAILABLE|Http::STATUS_GATEWAY_TIMEOUT
 * @template-extends JSONResponse<ApiErrorStatus, array{error: array{message: string, type: string, param: ?string, code: ?string}}, array<string, mixed>>
 */
class ApiErrorResponse extends JSONResponse {
	public function __construct(ApiException $exception) {
		parent::__construct($exception->toArray(), $exception->getStatus());
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Controller;

use OCA\OpenAiApi\Exception\ApiException;
use OCA\OpenAiApi\Response\ApiErrorResponse;
use OCP\AppFramework\Controller;

abstract class AbstractApiController extends Controller {
	protected function error(ApiException $exception): ApiErrorResponse {
		return new ApiErrorResponse($exception);
	}

	/**
	 * @throws ApiException
	 */
	protected function requireList(mixed $value, string $param): array {
		if (!is_array($value) || ($value !== [] && !array_is_list($value))) {
			throw ApiException::invalidRequest('"' . $param . '" must be an array.', $param);
		}
		return $value;
	}

	/**
	 * @throws ApiException
	 */
	protected function requireString(mixed $value, string $param): string {
		if (!is_string($value) || $value === '') {
			throw ApiException::invalidRequest('"' . $param . '" is required.', $param);
		}
		return $value;
	}

	protected function toBool(mixed $value): bool {
		return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true;
	}

	protected function toPositiveInt(mixed $value): ?int {
		if ($value === null || $value === '') {
			return null;
		}
		if (!is_numeric($value)) {
			return null;
		}
		$int = (int)$value;
		return $int > 0 ? $int : null;
	}
}

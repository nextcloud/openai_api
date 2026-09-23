<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Controller;

use OCA\OpenAiApi\Exception\ApiException;
use OCA\OpenAiApi\Service\TaskFileService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IRequest;
use OCP\TaskProcessing\Exception\NotFoundException;
use OCP\TaskProcessing\IManager;

/**
 * Serves files a task produced, for clients that asked for URLs instead of
 * inline data.
 */
class TaskFileController extends AbstractApiController {
	public function __construct(
		string $appName,
		IRequest $request,
		private IManager $taskProcessingManager,
		private TaskFileService $taskFileService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function get(int $taskId, int $fileId): StreamResponse|JSONResponse {
		try {
			try {
				$task = $this->taskProcessingManager->getUserTask($taskId, $this->userId);
			} catch (NotFoundException $e) {
				throw new ApiException('Task not found.', Http::STATUS_NOT_FOUND, 'invalid_request_error', null, null, $e);
			}

			$file = $this->taskFileService->getFile($task, $fileId);
			$handle = $file->fopen('rb');
			if ($handle === false) {
				throw ApiException::serverError('The file could not be opened.');
			}

			$response = new StreamResponse($handle);
			$response->addHeader('Content-Type', $this->taskFileService->getContentType($file));
			$response->addHeader(
				'Content-Disposition',
				'inline; filename="' . rawurlencode($file->getName()) . '"',
			);
			return $response;
		} catch (ApiException $e) {
			return $this->error($e);
		}
	}
}

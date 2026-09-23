<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

use OCA\OpenAiApi\Exception\ApiException;
use OCP\AppFramework\Http;
use OCP\TaskProcessing\Exception\Exception as TaskProcessingException;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\MultimodalChatWithTools;
use OCP\TaskProcessing\TaskTypes\TextToImage;
use OCP\TaskProcessing\TaskTypes\TextToSpeech;
use OCP\TaskProcessing\TaskTypes\TextToText;
use OCP\TaskProcessing\TaskTypes\TextToTextChat;
use OCP\TaskProcessing\TaskTypes\TextToTextChatWithTools;

/**
 * Exposes task types as OpenAI models.
 *
 * A task type is the unit an OpenAI client can meaningfully choose between: the
 * concrete model behind it is whichever provider the administrator prefers for
 * that task type.
 */
class ModelService {
	/** Chat task types, most capable first. */
	public const CHAT_TASK_TYPES = [
		MultimodalChatWithTools::ID,
		TextToTextChatWithTools::ID,
		TextToTextChat::ID,
		TextToText::ID,
	];
	public const COMPLETION_TASK_TYPES = [TextToText::ID];
	public const TRANSCRIPTION_TASK_TYPES = [AudioToText::ID];
	public const SPEECH_TASK_TYPES = [TextToSpeech::ID];
	public const IMAGE_TASK_TYPES = [TextToImage::ID];

	/** Task types this app exposes, in the order they are listed. */
	public const ALL_TASK_TYPES = [
		...self::CHAT_TASK_TYPES,
		...self::TRANSCRIPTION_TASK_TYPES,
		...self::SPEECH_TASK_TYPES,
		...self::IMAGE_TASK_TYPES,
	];

	public function __construct(
		private IManager $taskProcessingManager,
	) {
	}

	/**
	 * The task types that are available to the given user and that this app maps.
	 *
	 * @return list<string>
	 */
	public function getAvailableModels(?string $userId): array {
		$available = $this->taskProcessingManager->getAvailableTaskTypeIds(false, $userId);
		return array_values(array_filter(
			self::ALL_TASK_TYPES,
			static fn (string $taskTypeId): bool => in_array($taskTypeId, $available, true),
		));
	}

	/**
	 * @return list<array{id: string, object: string, created: int, owned_by: string}>
	 */
	public function listModels(?string $userId): array {
		return array_map(
			static fn (string $taskTypeId): array => [
				'id' => $taskTypeId,
				'object' => 'model',
				'created' => 0,
				'owned_by' => 'nextcloud',
			],
			$this->getAvailableModels($userId),
		);
	}

	/**
	 * Resolve the requested model to a task type usable for this endpoint.
	 *
	 * @param list<string> $candidates task types this endpoint can drive, preferred first
	 * @throws ApiException
	 */
	public function resolve(?string $model, array $candidates, ?string $userId): string {
		$available = $this->taskProcessingManager->getAvailableTaskTypeIds(false, $userId);
		$usable = array_values(array_filter(
			$candidates,
			static fn (string $taskTypeId): bool => in_array($taskTypeId, $available, true),
		));

		if ($model === null || $model === '') {
			if ($usable === []) {
				throw new ApiException(
					'No task processing provider is available for this endpoint.',
					Http::STATUS_SERVICE_UNAVAILABLE,
					'server_error',
					null,
					'no_provider',
				);
			}
			return $usable[0];
		}

		if (!in_array($model, $usable, true)) {
			throw ApiException::modelNotFound($model);
		}
		return $model;
	}

	/**
	 * The input slots a task type declares, required and optional.
	 *
	 * @return list<string>
	 * @throws ApiException
	 */
	public function getInputSlots(string $taskTypeId): array {
		try {
			$taskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
		} catch (TaskProcessingException $e) {
			throw ApiException::serverError('Could not read the available task types.', $e);
		}
		if (!isset($taskTypes[$taskTypeId])) {
			throw ApiException::modelNotFound($taskTypeId);
		}
		return [
			...array_keys($taskTypes[$taskTypeId]['inputShape']),
			...array_keys($taskTypes[$taskTypeId]['optionalInputShape']),
		];
	}
}

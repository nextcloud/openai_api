<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

use OCA\OpenAiApi\AppInfo\Application;
use OCA\OpenAiApi\Exception\ApiException;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\MultimodalChatWithTools;
use OCP\TaskProcessing\TaskTypes\TextToTextChatWithTools;

/**
 * Turns a chat completion request into a task for the resolved task type.
 *
 * Only the slots the task type actually declares are filled. Where dropping a
 * part of the request would change what the client asked for - tool definitions
 * a task type cannot carry, attachments it cannot take - the request is
 * rejected instead of quietly degraded. The exception is the conversation
 * itself, which is flattened into a single prompt for task types without a
 * history slot.
 */
class ChatTaskFactory {
	public function __construct(
		private ModelService $modelService,
		private ChatMessageMapper $chatMessageMapper,
		private FileStagingService $fileStagingService,
	) {
	}

	/**
	 * @param list<mixed> $messages
	 * @param list<mixed>|null $tools
	 * @throws ApiException
	 */
	public function create(
		?string $userId,
		?string $model,
		array $messages,
		?array $tools,
		?int $maxTokens,
	): PreparedTask {
		$taskTypeId = $this->modelService->resolve($model, ModelService::CHAT_TASK_TYPES, $userId);
		$slots = $this->modelService->getInputSlots($taskTypeId);
		$mapped = $this->chatMessageMapper->map($messages, $tools);

		if ($tools !== null && $tools !== [] && !in_array('tools', $slots, true)) {
			throw ApiException::invalidRequest(
				'The model "' . $taskTypeId . '" does not support tools. Use "'
				. TextToTextChatWithTools::ID . '" or "' . MultimodalChatWithTools::ID . '" instead.',
				'tools',
			);
		}
		if ($mapped->toolMessage !== '' && !in_array('tool_message', $slots, true)) {
			throw ApiException::invalidRequest(
				'The model "' . $taskTypeId . '" does not support tool results. Use "'
				. TextToTextChatWithTools::ID . '" or "' . MultimodalChatWithTools::ID . '" instead.',
				'messages',
			);
		}
		if ($mapped->attachments !== [] && !in_array('input_attachments', $slots, true)) {
			throw ApiException::invalidRequest(
				'The model "' . $taskTypeId . '" does not accept attachments. Use "'
				. MultimodalChatWithTools::ID . '" instead.',
				'messages',
			);
		}

		$stagedFileIds = $userId === null
			? []
			: $this->fileStagingService->stage($userId, $mapped->attachments);

		try {
			$input = $this->buildInput($mapped, $slots, $stagedFileIds, $maxTokens);
		} catch (ApiException $e) {
			$this->cleanup($userId, $stagedFileIds);
			throw $e;
		}

		return new PreparedTask(
			new Task($taskTypeId, $input, Application::APP_ID, $userId),
			$taskTypeId,
			$stagedFileIds,
		);
	}

	/**
	 * @param list<int> $stagedFileIds
	 */
	public function cleanup(?string $userId, array $stagedFileIds): void {
		if ($userId !== null) {
			$this->fileStagingService->cleanup($userId, $stagedFileIds);
		}
	}

	/**
	 * @param list<string> $slots
	 * @param list<int> $stagedFileIds
	 * @return array<string, mixed>
	 */
	private function buildInput(
		MappedChatRequest $mapped,
		array $slots,
		array $stagedFileIds,
		?int $maxTokens,
	): array {
		$hasHistory = in_array('history', $slots, true);
		$candidates = [
			'system_prompt' => $mapped->systemPrompt,
			'input' => $hasHistory ? $mapped->input : $this->chatMessageMapper->flattenToPrompt($mapped),
			'history' => $mapped->history,
			'tool_message' => $mapped->toolMessage,
			'tools' => $mapped->tools,
			'input_attachments' => $stagedFileIds,
		];
		if (!$hasHistory) {
			// The flattened prompt already carries the system prompt
			unset($candidates['system_prompt']);
		}

		$input = [];
		foreach ($candidates as $slot => $value) {
			if (in_array($slot, $slots, true)) {
				$input[$slot] = $value;
			}
		}
		if ($maxTokens !== null && in_array('max_tokens', $slots, true)) {
			$input['max_tokens'] = $maxTokens;
		}
		return $input;
	}
}

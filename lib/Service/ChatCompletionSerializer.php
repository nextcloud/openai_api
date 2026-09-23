<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

/**
 * Builds the OpenAI response objects for the chat and completion endpoints.
 *
 * Task Processing does not report token counts, so `usage` is reported as zero
 * rather than omitted - clients that read the field unconditionally would
 * otherwise fail.
 */
class ChatCompletionSerializer {
	private const EMPTY_USAGE = [
		'prompt_tokens' => 0,
		'completion_tokens' => 0,
		'total_tokens' => 0,
	];

	/**
	 * @param list<array<string, mixed>> $toolCalls
	 */
	public function completion(
		string $id,
		string $model,
		int $created,
		string $content,
		array $toolCalls = [],
		string $reasoning = '',
	): array {
		$message = [
			'role' => 'assistant',
			'content' => $content === '' && $toolCalls !== [] ? null : $content,
		];
		if ($toolCalls !== []) {
			$message['tool_calls'] = $toolCalls;
		}
		if ($reasoning !== '') {
			$message['reasoning_content'] = $reasoning;
		}

		return [
			'id' => $id,
			'object' => 'chat.completion',
			'created' => $created,
			'model' => $model,
			'choices' => [[
				'index' => 0,
				'message' => $message,
				'logprobs' => null,
				'finish_reason' => $toolCalls === [] ? 'stop' : 'tool_calls',
			]],
			'usage' => self::EMPTY_USAGE,
		];
	}

	/**
	 * @param array<string, mixed> $delta
	 */
	public function chunk(
		string $id,
		string $model,
		int $created,
		array $delta,
		?string $finishReason = null,
	): array {
		return [
			'id' => $id,
			'object' => 'chat.completion.chunk',
			'created' => $created,
			'model' => $model,
			'choices' => [[
				'index' => 0,
				'delta' => $delta,
				'logprobs' => null,
				'finish_reason' => $finishReason,
			]],
		];
	}

	/**
	 * The legacy /v1/completions response object.
	 */
	public function textCompletion(string $id, string $model, int $created, string $text): array {
		return [
			'id' => $id,
			'object' => 'text_completion',
			'created' => $created,
			'model' => $model,
			'choices' => [[
				'index' => 0,
				'text' => $text,
				'logprobs' => null,
				'finish_reason' => 'stop',
			]],
			'usage' => self::EMPTY_USAGE,
		];
	}
}

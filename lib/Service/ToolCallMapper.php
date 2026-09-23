<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

use OCA\OpenAiApi\Exception\ApiException;

/**
 * Translates between the OpenAI tool call representation and the one Task
 * Processing providers exchange.
 *
 * OpenAI nests the call under `function` and carries the arguments as a JSON
 * string: `{id, type: "function", function: {name, arguments: "{...}"}}`.
 * Providers use a flat object with decoded arguments instead:
 * `{id, name, args: {...}}`.
 */
class ToolCallMapper {
	/**
	 * Convert OpenAI tool calls from an assistant message into provider shape.
	 *
	 * @param list<array<string, mixed>> $toolCalls
	 * @return list<array<string, mixed>>
	 * @throws ApiException
	 */
	public function toProvider(array $toolCalls): array {
		$converted = [];
		foreach ($toolCalls as $toolCall) {
			if (!is_array($toolCall) || !isset($toolCall['function']) || !is_array($toolCall['function'])) {
				throw ApiException::invalidRequest('Malformed tool call in assistant message.', 'messages');
			}
			$function = $toolCall['function'];
			$arguments = $function['arguments'] ?? '{}';
			if (!is_string($arguments)) {
				throw ApiException::invalidRequest('Tool call arguments must be a JSON string.', 'messages');
			}
			// Decoded without assoc so that an empty argument object survives the
			// round trip as `{}` rather than turning into `[]`.
			$args = json_decode($arguments);
			if ($args === null && json_last_error() !== JSON_ERROR_NONE) {
				throw ApiException::invalidRequest('Tool call arguments are not valid JSON.', 'messages');
			}
			$converted[] = [
				'id' => (string)($toolCall['id'] ?? ($function['id'] ?? '')),
				'name' => (string)($function['name'] ?? ''),
				'args' => $args ?? new \stdClass(),
			];
		}
		return $converted;
	}

	/**
	 * Convert the `tool_calls` output slot of a task into OpenAI tool calls.
	 *
	 * @return list<array{id: string, type: string, function: array{name: string, arguments: string}}>
	 */
	public function fromProvider(string $toolCallsJson): array {
		if ($toolCallsJson === '') {
			return [];
		}
		$decoded = json_decode($toolCallsJson, true);
		if (!is_array($decoded) || !array_is_list($decoded)) {
			return [];
		}

		$converted = [];
		foreach ($decoded as $index => $toolCall) {
			if (!is_array($toolCall)) {
				continue;
			}
			$arguments = json_encode($toolCall['args'] ?? new \stdClass());
			if ($arguments === false || $arguments === '[]') {
				$arguments = '{}';
			}
			$id = (string)($toolCall['id'] ?? '');
			if ($id === '') {
				$id = 'call_' . $index;
			}
			$converted[] = [
				'id' => $id,
				'type' => 'function',
				'function' => [
					'name' => (string)($toolCall['name'] ?? ''),
					'arguments' => $arguments,
				],
			];
		}
		return $converted;
	}
}

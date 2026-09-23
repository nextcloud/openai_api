<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Tests\unit\Service;

use OCA\OpenAiApi\Service\ChatCompletionSerializer;
use Test\TestCase;

class ChatCompletionSerializerTest extends TestCase {
	private ChatCompletionSerializer $serializer;

	protected function setUp(): void {
		parent::setUp();
		$this->serializer = new ChatCompletionSerializer();
	}

	public function testCompletionFinishesWithStop(): void {
		$result = $this->serializer->completion('chatcmpl-1', 'core:text2text', 100, 'Hello');

		self::assertSame('chat.completion', $result['object']);
		self::assertSame('Hello', $result['choices'][0]['message']['content']);
		self::assertSame('stop', $result['choices'][0]['finish_reason']);
		self::assertArrayNotHasKey('tool_calls', $result['choices'][0]['message']);
		self::assertSame(0, $result['usage']['total_tokens']);
	}

	public function testCompletionWithToolCallsReportsNullContent(): void {
		$toolCalls = [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'x', 'arguments' => '{}']]];
		$result = $this->serializer->completion('chatcmpl-1', 'core:text2text', 100, '', $toolCalls);

		self::assertNull($result['choices'][0]['message']['content']);
		self::assertSame($toolCalls, $result['choices'][0]['message']['tool_calls']);
		self::assertSame('tool_calls', $result['choices'][0]['finish_reason']);
	}

	public function testCompletionCarriesReasoningWhenPresent(): void {
		$result = $this->serializer->completion('chatcmpl-1', 'm', 100, 'Hi', [], 'because');

		self::assertSame('because', $result['choices'][0]['message']['reasoning_content']);
	}

	public function testChunkShape(): void {
		$result = $this->serializer->chunk('chatcmpl-1', 'm', 100, ['content' => 'Hi'], null);

		self::assertSame('chat.completion.chunk', $result['object']);
		self::assertSame(['content' => 'Hi'], $result['choices'][0]['delta']);
		self::assertNull($result['choices'][0]['finish_reason']);
	}

	public function testTextCompletionShape(): void {
		$result = $this->serializer->textCompletion('cmpl-1', 'm', 100, 'Hi');

		self::assertSame('text_completion', $result['object']);
		self::assertSame('Hi', $result['choices'][0]['text']);
		self::assertSame('stop', $result['choices'][0]['finish_reason']);
	}
}

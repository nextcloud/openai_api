<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Tests\unit\Service;

use OCA\OpenAiApi\Exception\ApiException;
use OCA\OpenAiApi\Service\ChatMessageMapper;
use OCA\OpenAiApi\Service\ChatTaskFactory;
use OCA\OpenAiApi\Service\FileStagingService;
use OCA\OpenAiApi\Service\ModelService;
use OCA\OpenAiApi\Service\ToolCallMapper;
use OCP\TaskProcessing\TaskTypes\MultimodalChatWithTools;
use OCP\TaskProcessing\TaskTypes\TextToText;
use OCP\TaskProcessing\TaskTypes\TextToTextChatWithTools;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ChatTaskFactoryTest extends TestCase {
	private const CHAT_WITH_TOOLS_SLOTS = [
		'system_prompt', 'input', 'tool_message', 'history', 'tools', 'max_tokens',
	];
	private const MULTIMODAL_SLOTS = [
		'system_prompt', 'input', 'input_attachments', 'tool_message', 'history', 'tools',
	];

	private ModelService&MockObject $modelService;
	private FileStagingService&MockObject $fileStagingService;
	private ChatTaskFactory $factory;

	protected function setUp(): void {
		parent::setUp();
		$this->modelService = $this->createMock(ModelService::class);
		$this->fileStagingService = $this->createMock(FileStagingService::class);
		$this->factory = new ChatTaskFactory(
			$this->modelService,
			new ChatMessageMapper(new ToolCallMapper()),
			$this->fileStagingService,
		);
	}

	/**
	 * @param list<string> $slots
	 */
	private function expectTaskType(string $taskTypeId, array $slots): void {
		$this->modelService->method('resolve')->willReturn($taskTypeId);
		$this->modelService->method('getInputSlots')->willReturn($slots);
	}

	public function testFillsTheSlotsOfTheTaskType(): void {
		$this->expectTaskType(TextToTextChatWithTools::ID, self::CHAT_WITH_TOOLS_SLOTS);

		$prepared = $this->factory->create('alice', null, [
			['role' => 'system', 'content' => 'Be brief.'],
			['role' => 'user', 'content' => 'A'],
			['role' => 'assistant', 'content' => 'B'],
			['role' => 'user', 'content' => 'C'],
		], null, 256);

		$input = $prepared->task->getInput();
		self::assertSame('Be brief.', $input['system_prompt']);
		self::assertSame('C', $input['input']);
		self::assertCount(2, $input['history']);
		self::assertSame('', $input['tool_message']);
		self::assertSame('[]', $input['tools']);
		self::assertSame(256, $input['max_tokens']);
		self::assertSame(TextToTextChatWithTools::ID, $prepared->task->getTaskTypeId());
		self::assertSame('alice', $prepared->task->getUserId());
	}

	public function testOmitsMaxTokensWhenTheTaskTypeHasNoSuchSlot(): void {
		$this->expectTaskType(MultimodalChatWithTools::ID, self::MULTIMODAL_SLOTS);

		$prepared = $this->factory->create('alice', null, [['role' => 'user', 'content' => 'A']], null, 256);

		self::assertArrayNotHasKey('max_tokens', $prepared->task->getInput());
	}

	public function testFlattensTheConversationForTaskTypesWithoutHistory(): void {
		$this->expectTaskType(TextToText::ID, ['input']);

		$prepared = $this->factory->create('alice', null, [
			['role' => 'system', 'content' => 'Be brief.'],
			['role' => 'user', 'content' => 'A'],
			['role' => 'assistant', 'content' => 'B'],
			['role' => 'user', 'content' => 'C'],
		], null, null);

		self::assertSame(
			['input' => "Be brief.\n\nUser: A\n\nAssistant: B\n\nUser: C"],
			$prepared->task->getInput(),
		);
	}

	public function testStagesAttachmentsAndPassesTheirIds(): void {
		$this->expectTaskType(MultimodalChatWithTools::ID, self::MULTIMODAL_SLOTS);
		$this->fileStagingService->expects(self::once())
			->method('stage')
			->with('alice', self::countOf(1))
			->willReturn([42]);

		$prepared = $this->factory->create('alice', null, [
			['role' => 'user', 'content' => [
				['type' => 'text', 'text' => 'What is this?'],
				['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . base64_encode('PNG')]],
			]],
		], null, null);

		self::assertSame([42], $prepared->task->getInput()['input_attachments']);
		self::assertSame([42], $prepared->stagedFileIds);
	}

	public function testRejectsAttachmentsForTaskTypesThatCannotTakeThem(): void {
		$this->expectTaskType(TextToTextChatWithTools::ID, self::CHAT_WITH_TOOLS_SLOTS);

		$this->expectException(ApiException::class);
		$this->factory->create('alice', null, [
			['role' => 'user', 'content' => [
				['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,AAA']],
			]],
		], null, null);
	}

	public function testRejectsToolsForTaskTypesThatCannotTakeThem(): void {
		$this->expectTaskType(TextToText::ID, ['input']);

		$this->expectException(ApiException::class);
		$this->factory->create('alice', null, [['role' => 'user', 'content' => 'A']], [
			['type' => 'function', 'function' => ['name' => 'weather']],
		], null);
	}

	public function testRejectsToolResultsForTaskTypesThatCannotTakeThem(): void {
		$this->expectTaskType(TextToText::ID, ['input']);

		$this->expectException(ApiException::class);
		$this->factory->create('alice', null, [
			['role' => 'user', 'content' => 'A'],
			['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => 'x'],
		], null, null);
	}

	public function testDoesNotStageForAnonymousRequests(): void {
		$this->expectTaskType(MultimodalChatWithTools::ID, self::MULTIMODAL_SLOTS);
		$this->fileStagingService->expects(self::never())->method('stage');

		$prepared = $this->factory->create(null, null, [['role' => 'user', 'content' => 'A']], null, null);

		self::assertSame([], $prepared->task->getInput()['input_attachments']);
	}
}

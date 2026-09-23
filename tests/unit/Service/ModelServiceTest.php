<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Tests\unit\Service;

use OCA\OpenAiApi\Exception\ApiException;
use OCA\OpenAiApi\Service\ModelService;
use OCP\AppFramework\Http;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\MultimodalChatWithTools;
use OCP\TaskProcessing\TaskTypes\TextToText;
use OCP\TaskProcessing\TaskTypes\TextToTextChatWithTools;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ModelServiceTest extends TestCase {
	private IManager&MockObject $taskProcessingManager;
	private ModelService $modelService;

	protected function setUp(): void {
		parent::setUp();
		$this->taskProcessingManager = $this->createMock(IManager::class);
		$this->modelService = new ModelService($this->taskProcessingManager);
	}

	private function available(string ...$taskTypeIds): void {
		$this->taskProcessingManager->method('getAvailableTaskTypeIds')->willReturn($taskTypeIds);
	}

	public function testListModelsOnlyContainsMappedAndAvailableTaskTypes(): void {
		$this->available(TextToTextChatWithTools::ID, AudioToText::ID, 'core:text2text:summary');

		$ids = array_column($this->modelService->listModels('alice'), 'id');

		self::assertSame([TextToTextChatWithTools::ID, AudioToText::ID], $ids);
	}

	public function testListModelsUsesTheOpenAiModelShape(): void {
		$this->available(TextToText::ID);

		self::assertSame([[
			'id' => TextToText::ID,
			'object' => 'model',
			'created' => 0,
			'owned_by' => 'nextcloud',
		]], $this->modelService->listModels('alice'));
	}

	public function testResolveWithoutModelPicksTheMostCapableCandidate(): void {
		$this->available(TextToText::ID, TextToTextChatWithTools::ID, MultimodalChatWithTools::ID);

		self::assertSame(
			MultimodalChatWithTools::ID,
			$this->modelService->resolve(null, ModelService::CHAT_TASK_TYPES, 'alice'),
		);
	}

	public function testResolveHonoursAnExplicitModel(): void {
		$this->available(TextToText::ID, TextToTextChatWithTools::ID);

		self::assertSame(
			TextToText::ID,
			$this->modelService->resolve(TextToText::ID, ModelService::CHAT_TASK_TYPES, 'alice'),
		);
	}

	public function testResolveRejectsAnUnavailableModel(): void {
		$this->available(TextToText::ID);

		$this->expectException(ApiException::class);
		$this->modelService->resolve(TextToTextChatWithTools::ID, ModelService::CHAT_TASK_TYPES, 'alice');
	}

	public function testResolveRejectsAModelThatIsWrongForTheEndpoint(): void {
		$this->available(TextToText::ID, AudioToText::ID);

		$this->expectException(ApiException::class);
		$this->modelService->resolve(AudioToText::ID, ModelService::CHAT_TASK_TYPES, 'alice');
	}

	public function testResolveReportsWhenNoProviderIsAvailable(): void {
		$this->available();

		try {
			$this->modelService->resolve(null, ModelService::CHAT_TASK_TYPES, 'alice');
			self::fail('Expected an ApiException');
		} catch (ApiException $e) {
			self::assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $e->getStatus());
		}
	}

	public function testGetInputSlotsMergesRequiredAndOptionalShapes(): void {
		$this->taskProcessingManager->method('getAvailableTaskTypes')->willReturn([
			TextToTextChatWithTools::ID => [
				'inputShape' => ['input' => null, 'history' => null],
				'optionalInputShape' => ['max_tokens' => null],
			],
		]);

		self::assertSame(
			['input', 'history', 'max_tokens'],
			$this->modelService->getInputSlots(TextToTextChatWithTools::ID),
		);
	}

	public function testGetInputSlotsRejectsAnUnknownTaskType(): void {
		$this->taskProcessingManager->method('getAvailableTaskTypes')->willReturn([]);

		$this->expectException(ApiException::class);
		$this->modelService->getInputSlots('core:does-not-exist');
	}
}

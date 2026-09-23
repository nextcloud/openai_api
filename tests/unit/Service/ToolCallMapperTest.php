<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Tests\unit\Service;

use OCA\OpenAiApi\Exception\ApiException;
use OCA\OpenAiApi\Service\ToolCallMapper;
use Test\TestCase;

class ToolCallMapperTest extends TestCase {
	private ToolCallMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new ToolCallMapper();
	}

	public function testToProviderDecodesArguments(): void {
		$result = $this->mapper->toProvider([[
			'id' => 'call_1',
			'type' => 'function',
			'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Berlin"}'],
		]]);

		self::assertCount(1, $result);
		self::assertSame('call_1', $result[0]['id']);
		self::assertSame('get_weather', $result[0]['name']);
		self::assertSame('Berlin', $result[0]['args']->city);
	}

	public function testToProviderKeepsEmptyArgumentsAnObject(): void {
		$result = $this->mapper->toProvider([[
			'id' => 'call_1',
			'function' => ['name' => 'now', 'arguments' => '{}'],
		]]);

		self::assertSame('{"id":"call_1","name":"now","args":{}}', json_encode($result[0]));
	}

	public function testToProviderRejectsMalformedCall(): void {
		$this->expectException(ApiException::class);
		$this->mapper->toProvider([['id' => 'call_1']]);
	}

	public function testToProviderRejectsInvalidJsonArguments(): void {
		$this->expectException(ApiException::class);
		$this->mapper->toProvider([[
			'id' => 'call_1',
			'function' => ['name' => 'x', 'arguments' => 'not json'],
		]]);
	}

	public function testFromProviderBuildsOpenAiShape(): void {
		$result = $this->mapper->fromProvider('[{"name":"get_weather","id":"call_1","args":{"city":"Berlin"}}]');

		self::assertSame([[
			'id' => 'call_1',
			'type' => 'function',
			'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Berlin"}'],
		]], $result);
	}

	public function testFromProviderNormalisesEmptyArguments(): void {
		$result = $this->mapper->fromProvider('[{"name":"now","id":"call_1","args":[]}]');

		self::assertSame('{}', $result[0]['function']['arguments']);
	}

	public function testFromProviderSynthesisesMissingId(): void {
		$result = $this->mapper->fromProvider('[{"name":"now","args":{}}]');

		self::assertSame('call_0', $result[0]['id']);
	}

	public function testFromProviderHandlesEmptyAndInvalidInput(): void {
		self::assertSame([], $this->mapper->fromProvider(''));
		self::assertSame([], $this->mapper->fromProvider('{"not":"a list"}'));
	}

	public function testRoundTrip(): void {
		$openAi = [[
			'id' => 'call_1',
			'type' => 'function',
			'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Berlin"}'],
		]];

		$provider = json_encode($this->mapper->toProvider($openAi));
		self::assertIsString($provider);
		self::assertSame($openAi, $this->mapper->fromProvider($provider));
	}
}

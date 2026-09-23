<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Tests\unit\Service;

use OCA\OpenAiApi\Service\StreamCursor;
use Test\TestCase;

class StreamCursorTest extends TestCase {
	public function testCumulativeSnapshotsYieldDeltas(): void {
		$cursor = new StreamCursor();

		self::assertSame('Hello', $cursor->advance('Hello'));
		self::assertSame(' world', $cursor->advance('Hello world'));
		self::assertSame('!', $cursor->advance('Hello world!'));
	}

	public function testUnchangedSnapshotYieldsNothing(): void {
		$cursor = new StreamCursor();
		$cursor->advance('Hello');

		self::assertSame('', $cursor->advance('Hello'));
	}

	public function testNonCumulativeSnapshotIsTreatedAsFreshText(): void {
		$cursor = new StreamCursor();
		$cursor->advance('Hello');

		self::assertSame('something else', $cursor->advance('something else'));
		self::assertSame('something else', $cursor->getEmitted());
	}

	public function testEmptySnapshotYieldsNothing(): void {
		$cursor = new StreamCursor();

		self::assertSame('', $cursor->advance(''));
	}
}

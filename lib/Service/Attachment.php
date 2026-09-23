<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

/**
 * Decoded binary content of an inline attachment, before it is staged as a file.
 */
class Attachment {
	public function __construct(
		public readonly string $content,
		public readonly string $filename,
	) {
	}
}

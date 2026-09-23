<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

/**
 * An OpenAI chat request expressed in the slots of the chat task types.
 */
class MappedChatRequest {
	/**
	 * @param list<string> $history JSON encoded message objects
	 * @param list<Attachment> $attachments inline attachments of the final user message
	 */
	public function __construct(
		public readonly string $systemPrompt,
		public readonly string $input,
		public readonly array $history,
		public readonly string $toolMessage,
		public readonly string $tools,
		public readonly array $attachments,
	) {
	}
}

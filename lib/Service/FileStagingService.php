<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Service;

use OCA\OpenAiApi\AppInfo\Application;
use OCA\OpenAiApi\Exception\ApiException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Stages inline attachments as files in the requesting user's storage.
 *
 * Task Processing resolves file slots through the user mount cache, so files in
 * appdata are rejected for task input - attachments have to live somewhere the
 * user has a mount for. They are written to a hidden folder in the user's root
 * and removed again once the request that needs them is done.
 */
class FileStagingService {
	public function __construct(
		private IRootFolder $rootFolder,
		private Config $config,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param list<Attachment> $attachments
	 * @return list<int> the ids of the staged files
	 * @throws ApiException
	 */
	public function stage(string $userId, array $attachments): array {
		if ($attachments === []) {
			return [];
		}

		try {
			$folder = $this->getStagingFolder($userId);
		} catch (\Throwable $e) {
			throw ApiException::serverError('Could not prepare the attachment staging folder.', $e);
		}

		$fileIds = [];
		try {
			foreach ($attachments as $attachment) {
				$name = uniqid('', true) . '-' . $this->sanitize($attachment->filename);
				$file = $folder->newFile($name, $attachment->content);
				$fileIds[] = $file->getId();
			}
		} catch (\Throwable $e) {
			$this->cleanup($userId, $fileIds);
			throw ApiException::serverError('Could not store an attachment.', $e);
		}

		return $fileIds;
	}

	/**
	 * Delete staged files. Never throws - a leftover file is swept later.
	 *
	 * @param list<int> $fileIds
	 */
	public function cleanup(string $userId, array $fileIds): void {
		if ($fileIds === []) {
			return;
		}
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not open the user folder to clean up staged files', ['exception' => $e]);
			return;
		}
		foreach ($fileIds as $fileId) {
			try {
				$node = $userFolder->getFirstNodeById($fileId);
				$node?->delete();
			} catch (\Throwable $e) {
				$this->logger->warning('Could not delete staged file ' . $fileId, ['exception' => $e]);
			}
		}
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\User\Exceptions\UserNotFoundException
	 */
	private function getStagingFolder(string $userId): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		try {
			$folder = $userFolder->get(Application::STAGING_FOLDER);
			if (!$folder instanceof Folder) {
				throw new NotFoundException('Staging path is not a folder');
			}
		} catch (NotFoundException) {
			return $userFolder->newFolder(Application::STAGING_FOLDER);
		}

		$this->sweep($folder);
		return $folder;
	}

	/**
	 * Drop files left behind by requests that died before they could clean up.
	 *
	 * Sweeping the requesting user's own folder on every request keeps this
	 * bounded and self-healing, without a job that would have to walk every
	 * account on the instance.
	 */
	private function sweep(Folder $folder): void {
		$expiry = $this->timeFactory->getTime() - $this->config->getStagedFileTtl();
		try {
			foreach ($folder->getDirectoryListing() as $node) {
				if ($node instanceof File && $node->getMTime() < $expiry) {
					$node->delete();
				}
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Could not sweep the attachment staging folder', ['exception' => $e]);
		}
	}

	private function sanitize(string $filename): string {
		$sanitized = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'attachment';
		$sanitized = ltrim($sanitized, '.');
		if ($sanitized === '') {
			$sanitized = 'attachment';
		}
		return substr($sanitized, 0, 100);
	}
}

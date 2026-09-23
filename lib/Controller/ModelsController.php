<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OpenAiApi\Controller;

use OCA\OpenAiApi\Exception\ApiException;
use OCA\OpenAiApi\Service\ModelService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ModelsController extends AbstractApiController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ModelService $modelService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return new JSONResponse([
			'object' => 'list',
			'data' => $this->modelService->listModels($this->userId),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(string $model): JSONResponse {
		$models = $this->modelService->listModels($this->userId);
		foreach ($models as $entry) {
			if ($entry['id'] === $model) {
				return new JSONResponse($entry);
			}
		}
		return $this->error(ApiException::modelNotFound($model));
	}
}

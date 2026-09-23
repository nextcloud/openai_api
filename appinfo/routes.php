<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
return [
	'routes' => [
		['name' => 'Models#index', 'url' => '/v1/models', 'verb' => 'GET'],
		['name' => 'Models#show', 'url' => '/v1/models/{model}', 'verb' => 'GET', 'requirements' => ['model' => '.+']],
		['name' => 'Chat#completions', 'url' => '/v1/chat/completions', 'verb' => 'POST'],
		['name' => 'Completions#create', 'url' => '/v1/completions', 'verb' => 'POST'],
		['name' => 'Audio#transcriptions', 'url' => '/v1/audio/transcriptions', 'verb' => 'POST'],
		['name' => 'Audio#speech', 'url' => '/v1/audio/speech', 'verb' => 'POST'],
		['name' => 'Images#generations', 'url' => '/v1/images/generations', 'verb' => 'POST'],
		['name' => 'TaskFile#get', 'url' => '/v1/task-files/{taskId}/{fileId}', 'verb' => 'GET'],
	],
];

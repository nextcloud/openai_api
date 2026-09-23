/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'

const isProduction = process.env.NODE_ENV === 'production'

export default createAppConfig({
	'settings-admin': 'src/settings-admin.ts',
}, {
	config: {
		css: {
			preprocessorOptions: {
				scss: {
					api: 'modern-compiler',
				},
			},
		},
	},
	inlineCSS: { relativeCSSInjection: true },
	minify: isProduction,
})

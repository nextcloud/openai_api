# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
app_name=openai_api

project_dir=$(CURDIR)
build_dir=$(CURDIR)/build
cert_dir=$(HOME)/.nextcloud/certificates

npm=$(shell which npm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)

all: build

.PHONY: build
build: composer npm

.PHONY: dev
dev: composer npm-dev

# Installs the PHP dependencies, including the tooling in vendor-bin.
.PHONY: composer
composer:
ifeq (, $(composer))
	$(error No composer command available, install it from https://getcomposer.org)
else
	composer install --prefer-dist
endif

# Installs the node dependencies and builds the frontend for production.
.PHONY: npm
npm:
	$(npm) ci
	$(npm) run build

.PHONY: npm-dev
npm-dev:
	$(npm) ci
	$(npm) run dev

.PHONY: lint
lint:
	composer run lint
	composer run cs:check
	composer run psalm
	$(npm) run lint
	$(npm) run stylelint

.PHONY: test
test:
	composer run test:unit

.PHONY: clean
clean:
	rm -rf $(build_dir) js node_modules vendor vendor-bin/*/vendor

# Builds a release tarball. Requires krankerl: https://github.com/ChristophWurst/krankerl
.PHONY: appstore
appstore:
	krankerl package

.PHONY: sign
sign:
	openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key \
		$(build_dir)/artifacts/$(app_name).tar.gz | openssl base64

# easy-excel build & test entry points. Docker-first: `make test` and
# `make build` run everything in containers; the host-* targets are for
# quick local iteration when the toolchains happen to be installed.

GO_PKGS := ./registry/... ./limits/... ./exio/... ./compat/... ./core/...
IMAGE   ?= frankenphp-easy-excel

.PHONY: test go-test php-test build host-test host-go-test host-php-test vet fmt generate host-build bench clean

test: go-test php-test

go-test:
	docker build --target=go-test --progress=plain .

php-test:
	docker build --target=php-test --progress=plain .

# full custom FrankenPHP with the extension baked in
build:
	docker build -t $(IMAGE) .

host-test: host-go-test host-php-test

host-go-test:
	cd extension && go vet $(GO_PKGS) && go test -race $(GO_PKGS)

host-php-test:
	php php/tests/run.php

vet:
	cd extension && go vet $(GO_PKGS)

# easy_excel.go is excluded: gofmt mangles //export_php: directives
# (underscore tool names are not recognized as directives).
fmt:
	cd extension && gofmt -w registry limits exio compat core tools

# Generates build/ (CGO wrappers, arginfo, stubs) from the bridge directives.
# GEN_STUB_SCRIPT must point at php-src's build/gen_stub.php checkout.
generate:
	cd extension && frankenphp extension-init easy_excel.go

host-build: generate
	cd extension && CGO_ENABLED=1 \
		XCADDY_GO_BUILD_FLAGS="-ldflags='-w -s' -tags=nobadger,nomysql,nopgx" \
		CGO_CFLAGS="$$(php-config --includes)" \
		CGO_LDFLAGS="$$(php-config --ldflags) $$(php-config --libs)" \
		xcaddy build --output ../frankenphp \
		--with github.com/dunglas/frankenphp/caddy \
		--with github.com/xiidea/easy-excel/extension=$$(pwd)

# competitor baselines run in the bench image (bench/Dockerfile adds the
# ext-zip they need); the easy-excel lane runs inside the built image
# (frankenphp php-cli). Both mount the shim at /php — bench/composer.json
# autoloads EasyExcel\ from ../php/src, i.e. /php/src inside the container.
bench: build
	docker build -t easy-excel-bench bench/
	docker run --rm -v $(PWD)/bench:/bench -w /bench composer:2 sh -c "composer install --quiet"
	docker run --rm -v $(PWD)/bench:/bench -v $(PWD)/php:/php -w /bench easy-excel-bench ./run.sh 10000 100000
	docker run --rm -v $(PWD)/bench:/bench -v $(PWD)/php:/php -w /bench \
		-e EASY_EXCEL_PHP="frankenphp php-cli" $(IMAGE) \
		sh -c 'for lib in easy-excel easy-excel-native; do for rows in 10000 100000; do frankenphp php-cli run.php $$lib write $$rows; done; done'

clean:
	rm -rf extension/build frankenphp bench/vendor bench/results.csv

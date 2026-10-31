# CI validation

`.github/workflows/homeboy.yml` runs `review lint` and `review test` on pull
requests and pushes to main, with differential gating. Homeboy resolves sibling
plugins from `validation_dependencies` in `homeboy.json`; do not duplicate those
checkouts in workflow YAML.

The managed suite uses an external MySQL service, matching extrachill-users.
Local full-suite runs therefore also need MySQL and the `WP_CODEBOX_DB_HOST`,
`WP_CODEBOX_DB_PORT`, `WP_CODEBOX_DB_USER`, and `WP_CODEBOX_DB_PASSWORD` environment
variables. SQLite is no longer the component default. The PHPUnit configuration
no longer excludes `requires-mysql` tests.

The separate artist-binding MySQL workflow is retained: its two-process proof
uses an installed WordPress database shared by worker processes, rather than
the managed PHPUnit transaction. It already explicitly installs its own PHPUnit
runner, so it does not depend on this component's Composer dev dependencies.

Standalone link-pages fixtures resolve the sibling `extrachill-link-pages`
plugin that Homeboy mounts under `wp-content/plugins`, or an explicit
`LINK_PAGES_WORKTREE` override. Fake-runtime fixtures do not require that
checkout. Real-runtime fixtures fail clearly when a declared dependency is
missing instead of skipping because of a VPS-specific absolute path.

The real-runtime smoke defaults to activation when invoked without arguments.
It does not need WordPress core source: its activation, site activation, and
external modes pass against link-pages main using their standalone stubs.

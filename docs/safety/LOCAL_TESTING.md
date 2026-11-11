# Local safety baseline

This preparation does not enable APIs or change website behavior.

## Verified backup set

Restricted local directory: `G:/friendie_logistics_safety/phase2a_20261111_164222`.

- `source-before.zip`: original source and assets; every entry checked against SHA-256.
- `uploads-before.zip`: existing upload/private-storage files; every entry checked.
- `configuration-private.zip`: original environment and configuration; never commit or publish.
- `original-files.json`: original project file checksums (excluding dependencies/runtime caches).
- `friendie_logistics-current.sql`: consistent InnoDB snapshot, including available database objects.
- `schema-baseline.sql`: schema only; no customer records.
- `database-before.json` and `database-verification.json`: schema/data fingerprints and restore results.
- `testing-credentials-private.json`: local restricted test credentials; never publish.

Dependencies (`vendor`, `node_modules`) and disposable caches/logs were excluded. Dependency
manifests/lockfiles are included. Historical SQL files were left unchanged; the new dump is
the verified database recovery artifact. Reparse-point directories were not followed.

The original source archive preserves two layout templates excluded from Git because they
contain embedded third-party API keys. Git alone is not a complete deployment backup.

## Databases

- Protected original: `friendie_logistics`.
- Restricted recovery copy: `friendie_logistics_restore_verify_20261111_164222`.
- Empty, schema-only test target: `friendie_logistics_testing_20261111_164222`.

Neither new database should be automatically deleted. Recovery contains private customer
data and must remain local/restricted. Never point the application at it accidentally.

The test account has privileges only on its test database. Preflight checks reject global
privileges, conflicting DB environment overrides, the original database, and non-loopback
hosts. The original database cannot even be selected through the test account.

## Run only the safety smoke tests initially

```powershell
php vendor/bin/phpunit --configuration phpunit.xml --filter SafetyEnvironmentTest --do-not-cache-result
```

`tests/bootstrap.php` requires ignored `.env.testing` credentials and checks privileges before
Laravel boots. `tests/TestCase.php` redirects storage, public paths, relative upload working
directory, compiled views and bootstrap caches into a unique temporary directory. The
test-only provider fakes Laravel mail, notifications, bus and queues and prevents unmocked
Laravel HTTP requests. Only the restricted MySQL connection is available in test config.

These are Laravel-client safeguards, not an operating-system network sandbox. Direct cURL,
raw sockets, subprocesses or deliberately reading the original `.env` require separate
review and must not be introduced into tests. Never run privileged Artisan/database tools
as a substitute for this test bootstrap. Do not reuse application credentials in tests.

The original migration files do not reproduce the database. `RefreshDatabase`,
`DatabaseMigrations` and `DatabaseTruncation` are deliberately rejected before their setup
runs. Existing legacy tests using them remain unvalidated. Use `DatabaseTransactions` on
the schema-only baseline with synthetic fixtures; do not run the full suite automatically.

Temporary test directories are retained for inspection; any cleanup must target their exact
identities, not application storage. Test rows are rolled back; auto-increment counters in
the disposable test database may advance normally.

## Schema differences to preserve until separately reviewed

| Actual database | Historical migration |
| --- | --- |
| `countries.currency_name` exists | Missing |
| `post_ads.currency` exists | Missing |
| `post_ads.from_area` and `to_area` are varchar | Integer |
| Messages use nullable `read_by` | Defines `read_on` |
| `post_ads.isActive` defaults to 0 | Defaults to 1 |

No pending migration filenames were found. This does not prove historical schema equivalence.
The baseline retains actual defaults, types, indexes and constraints. No correction was made.

## Recovery procedure

1. Confirm source/destination identities and archive checksums.
2. Restore source/config/uploads into a new isolated location; preserve the original APP_KEY.
3. Restore the SQL dump into a new, absent database using an account restricted to that target.
4. Compare structures, row counts and ordered data fingerprints with the recorded snapshot.
5. Restore dependencies from lockfiles and perform isolated web/admin smoke checks with
   outbound integrations blocked before changing any application connection.

For future API rollback, revert the API release/exposure first. Never overwrite the original
database with an old snapshot while discarding legitimate newer website transactions.

# Loads database/dumps/catalog.sql into the database. Run migrations first.
# Windows PowerShell version.
# Keep this file ASCII-only: Windows PowerShell 5.1 reads BOM-less scripts as ANSI.
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..\..')

$dump = 'database\dumps\catalog.sql'
if (-not (Test-Path $dump)) {
    throw "File $dump not found. Run docker\db\catalog-dump.ps1 first."
}

docker compose cp $dump db:/tmp/catalog.sql
if ($LASTEXITCODE -ne 0) { throw 'Failed to copy dump into container' }

docker compose exec -T db sh -c 'psql -v ON_ERROR_STOP=1 -q -U $POSTGRES_USER -d $POSTGRES_DB -f /tmp/catalog.sql'
if ($LASTEXITCODE -ne 0) { throw 'psql failed' }

docker compose exec -T db rm -f /tmp/catalog.sql | Out-Null
Write-Host 'Catalog restored'

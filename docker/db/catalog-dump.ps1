# Dumps catalog data (chapters, categories, products) to database/dumps/catalog.sql.
# Data only: the schema is created by migrations. Windows PowerShell version.
# Keep this file ASCII-only: Windows PowerShell 5.1 reads BOM-less scripts as ANSI.
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..\..')

$utf8 = New-Object System.Text.UTF8Encoding($false)
$out = Join-Path (Get-Location) 'database\dumps\catalog.sql'
$tmp = Join-Path $env:TEMP 'silver-udochki-catalog-data.sql'
New-Item -ItemType Directory -Force -Path (Split-Path $out) | Out-Null

docker compose exec -T db sh -c 'pg_dump -U $POSTGRES_USER -d $POSTGRES_DB --data-only --column-inserts --no-owner --no-privileges -t public.chapters -t public.categories -t public.products -f /tmp/catalog-data.sql'
if ($LASTEXITCODE -ne 0) { throw 'pg_dump failed' }

docker compose cp db:/tmp/catalog-data.sql $tmp
if ($LASTEXITCODE -ne 0) { throw 'Failed to copy dump from container' }

$data = [System.IO.File]::ReadAllText($tmp, $utf8) -replace "`r`n", "`n"

$header = "BEGIN;`nTRUNCATE public.products, public.categories, public.chapters RESTART IDENTITY CASCADE;`n"
$footer = ''
foreach ($t in 'chapters', 'categories', 'products') {
    $footer += "SELECT setval(pg_get_serial_sequence('public.$t', 'id'), COALESCE((SELECT MAX(id) FROM public.$t), 1));`n"
}
$footer += "COMMIT;`n"

[System.IO.File]::WriteAllText($out, $header + $data + $footer, $utf8)
Remove-Item $tmp -ErrorAction SilentlyContinue
docker compose exec -T db rm -f /tmp/catalog-data.sql | Out-Null

$count = ([regex]::Matches($data, 'INSERT INTO public\.products')).Count
Write-Host "Done: $out ($count products)"

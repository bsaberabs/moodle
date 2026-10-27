[CmdletBinding()]
param(
    [string]$Version = '',
    [switch]$Force
)

$ErrorActionPreference = 'Stop'

$pluginroot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$versionfile = Join-Path $pluginroot 'version.php'

function Get-ReleaseRoot {
    $localparent = Split-Path -Parent $pluginroot
    $publicparent = Split-Path -Parent $localparent
    if (
        (Split-Path -Leaf $pluginroot) -eq 'moderncommerce' -and
        (Split-Path -Leaf $localparent) -eq 'local' -and
        (Split-Path -Leaf $publicparent) -eq 'public'
    ) {
        return (Resolve-Path (Join-Path $pluginroot '..\..\..')).Path
    }

    return $pluginroot
}

function Get-ReleaseVersion {
    $content = Get-Content -Raw -Path $versionfile
    if ($content -match '\$plugin->release\s*=\s*[''"]([^''"]+)[''"]\s*;') {
        return $Matches[1]
    }

    throw 'Unable to read $plugin->release from version.php.'
}

if ($Version.Trim() -eq '') {
    $Version = Get-ReleaseVersion
}

$releaseroot = Get-ReleaseRoot
$releasedir = Join-Path $releaseroot 'releases'
New-Item -ItemType Directory -Force -Path $releasedir | Out-Null

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw "Required command 'composer' was not found on PATH."
}

$docsroot = Join-Path $pluginroot 'docs'
if (Test-Path $docsroot) {
    if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
        throw "Required command 'node' was not found on PATH."
    }

    Write-Host '==> Documentation check'
    Push-Location $pluginroot
    try {
        & node 'scripts/check-docs.mjs'
        if ($LASTEXITCODE -ne 0) {
            throw "Documentation check failed with exit code $LASTEXITCODE."
        }
    } finally {
        Pop-Location
    }
} else {
    Write-Host '==> Documentation check skipped; docs folder is not present.'
}

$zip = Join-Path $releasedir "moderncommerce-v$Version.zip"
if ((Test-Path $zip) -and -not $Force) {
    throw "Release ZIP already exists: $zip. Re-run with -Force to overwrite it."
}
if (Test-Path $zip) {
    Remove-Item -LiteralPath $zip -Force
}

$temproot = Join-Path $env:TEMP ('moderncommerce-release-' + [guid]::NewGuid().ToString('N'))
$tempplugin = Join-Path $temproot 'moderncommerce'
New-Item -ItemType Directory -Force -Path $tempplugin | Out-Null
$temproot = (Resolve-Path -LiteralPath $temproot).Path
$tempplugin = (Resolve-Path -LiteralPath $tempplugin).Path

try {
    $excludednames = @(
        '.git',
        '.githooks',
        '.github',
        '.gitignore',
        'releases',
        'node_modules',
        'site',
        'vendor',
        '.env',
        '.env.local',
        '.phpunit.result.cache'
    )

    Get-ChildItem -LiteralPath $pluginroot -Force |
        Where-Object { $_.Name -notin $excludednames -and $_.Name -notlike '.codex-*' } |
        ForEach-Object {
            Copy-Item -LiteralPath $_.FullName -Destination $tempplugin -Recurse -Force
        }

    Write-Host '==> Composer production dependencies'
    Push-Location $tempplugin
    try {
        & composer install --no-dev --optimize-autoloader --no-interaction
        if ($LASTEXITCODE -ne 0) {
            throw "Composer install failed with exit code $LASTEXITCODE."
        }
    } finally {
        Pop-Location
    }

    $autoload = Join-Path $tempplugin 'vendor/autoload.php'
    if (-not (Test-Path $autoload)) {
        throw 'Release ZIP cannot be built without vendor/autoload.php.'
    }

    $license = Join-Path $tempplugin 'LICENSE'
    if (-not (Test-Path $license)) {
        throw 'Release ZIP cannot be built without the plugin root LICENSE file.'
    }
    $licensecontent = Get-Content -Raw -LiteralPath $license
    if ($licensecontent -notmatch 'GNU GENERAL PUBLIC LICENSE') {
        throw 'Release ZIP must contain the GNU General Public License.'
    }

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zipstream = [System.IO.File]::Open(
        $zip,
        [System.IO.FileMode]::CreateNew,
        [System.IO.FileAccess]::Write,
        [System.IO.FileShare]::None
    )
    $ziparchive = [System.IO.Compression.ZipArchive]::new(
        $zipstream,
        [System.IO.Compression.ZipArchiveMode]::Create
    )
    try {
        Get-ChildItem -LiteralPath $temproot -Recurse -File |
            ForEach-Object {
                $normalizedpath = $_.FullName.Replace('\', '/')
                $pluginmarker = '/moderncommerce/'
                $markerindex = $normalizedpath.IndexOf(
                    $pluginmarker,
                    [System.StringComparison]::OrdinalIgnoreCase
                )
                if ($markerindex -lt 0) {
                    throw "Release source file is outside the staged plugin directory: $normalizedpath"
                }
                $relativepath = $normalizedpath.Substring($markerindex + $pluginmarker.Length)
                $entryname = 'moderncommerce/' + $relativepath
                $entry = $ziparchive.CreateEntry(
                    $entryname,
                    [System.IO.Compression.CompressionLevel]::Optimal
                )
                $inputstream = [System.IO.File]::OpenRead($_.FullName)
                $entrystream = $entry.Open()
                try {
                    $inputstream.CopyTo($entrystream)
                } finally {
                    $entrystream.Dispose()
                    $inputstream.Dispose()
                }
            }
    } finally {
        $ziparchive.Dispose()
        $zipstream.Dispose()
    }

    $archive = [System.IO.Compression.ZipFile]::OpenRead($zip)
    try {
        $entrynames = @($archive.Entries |
            Where-Object { $_.FullName -ne '' } |
            ForEach-Object { $_.FullName.Replace('\', '/') })
        $invalidseparators = @($archive.Entries |
            Where-Object { $_.FullName.Contains('\') })
        if ($invalidseparators.Count -gt 0) {
            throw 'Release ZIP entries must use portable forward-slash path separators.'
        }
        $topdirs = @($entrynames |
            ForEach-Object { ($_ -split '/')[0] } |
            Sort-Object -Unique)
        if ($topdirs.Count -ne 1 -or $topdirs[0] -ne 'moderncommerce') {
            throw 'Release ZIP must contain exactly one top-level moderncommerce/ directory.'
        }
        if (-not ($entrynames | Where-Object { $_ -eq 'moderncommerce/version.php' })) {
            throw 'Release ZIP is missing moderncommerce/version.php.'
        }
        if (-not ($entrynames | Where-Object { $_ -eq 'moderncommerce/LICENSE' })) {
            throw 'Release ZIP is missing moderncommerce/LICENSE.'
        }
        if (-not ($entrynames | Where-Object { $_ -eq 'moderncommerce/vendor/autoload.php' })) {
            throw 'Release ZIP is missing moderncommerce/vendor/autoload.php.'
        }
        if (-not ($entrynames | Where-Object { $_ -like 'moderncommerce/docs/*.md' })) {
            throw 'Release ZIP is missing moderncommerce/docs/*.md documentation files.'
        }
    } finally {
        $archive.Dispose()
    }
} finally {
    if (Test-Path $temproot) {
        Remove-Item -LiteralPath $temproot -Recurse -Force
    }
}

$file = Get-Item $zip
Write-Host "Release ZIP written: $($file.FullName)"
Write-Host "Size: $($file.Length) bytes"

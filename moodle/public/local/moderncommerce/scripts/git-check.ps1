[CmdletBinding()]
param(
    [switch]$Fast,
    [switch]$SkipBuild,
    [switch]$SkipPhpUnit,
    [switch]$PhpcsReportOnly,
    [switch]$IncludeMustache
)

$ErrorActionPreference = 'Stop'

$pluginroot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$checkoutroot = (Resolve-Path (Join-Path $pluginroot '..\..\..')).Path
$pluginrelative = $pluginroot.Substring($checkoutroot.Length).TrimStart('\', '/').Replace('\', '/')
$phpunitsuite = 'local_moderncommerce_testsuite'

function Assert-Command {
    param([string]$Name)

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Required command '$Name' was not found on PATH."
    }
}

function Invoke-NativeStep {
    param(
        [string]$Name,
        [string]$WorkingDirectory,
        [string]$File,
        [string[]]$Arguments = @()
    )

    Write-Host "==> $Name"
    Push-Location $WorkingDirectory
    try {
        & $File @Arguments
        $exitcode = $LASTEXITCODE
        if ($exitcode -ne 0) {
            throw "$Name failed with exit code $exitcode."
        }
    } finally {
        Pop-Location
    }
}

function Invoke-PhpLint {
    Write-Host '==> PHP syntax lint'
    Push-Location $pluginroot
    try {
        $errors = @()
        Get-ChildItem -Recurse -Filter *.php -File |
            Where-Object {
                $_.FullName -notmatch '\\vendor\\' -and
                $_.FullName -notmatch '\\.codex-[^\\]+\\'
            } |
            ForEach-Object {
                $output = & php -l $_.FullName 2>&1
                if ($LASTEXITCODE -ne 0) {
                    $errors += $output
                }
            }

        if ($errors.Count -gt 0) {
            $errors | ForEach-Object { Write-Error $_ }
            throw 'PHP syntax lint failed.'
        }

        Write-Host 'No syntax errors detected in plugin PHP files.'
    } finally {
        Pop-Location
    }
}

function Invoke-MustacheLint {
    Write-Host '==> Mustache lint'
    $shimdir = Join-Path $env:TEMP 'moodle-plugin-ci-env-shim'
    New-Item -ItemType Directory -Force -Path $shimdir | Out-Null
    $shim = Join-Path $shimdir 'env.cmd'
    @'
@echo off
setlocal enabledelayedexpansion
:strip
if "%~1"=="-u" (
  shift
  shift
  goto strip
)
if "%~1"=="" exit /b 0
set "cmd=%~1"
shift
set "args="
:collect
if "%~1"=="" goto run
set "args=!args! "%~1""
shift
goto collect
:run
"%cmd%" !args!
'@ | Set-Content -Path $shim -Encoding ASCII

    $previouspath = $env:PATH
    $env:PATH = "$shimdir;$env:PATH"
    try {
        Invoke-NativeStep `
            -Name 'Moodle Plugin CI Mustache lint' `
            -WorkingDirectory $checkoutroot `
            -File 'moodle-plugin-ci' `
            -Arguments @('mustache', '--moodle=public', $pluginrelative)
    } finally {
        $env:PATH = $previouspath
    }
}

function Invoke-LegacyButtonGuard {
    Write-Host '==> Legacy Bootstrap button class guard'

    $allowedfiles = @(
        'classes/branding.php',
        'styles/scss/runtime/_compatibility.scss'
    )
    $ignoredroots = @(
        'vendor/',
        'node_modules/',
        '.codex-',
        'tests/',
        'releases/',
        'site/',
        'videos/',
        'amd/build/',
        'js/esm/build/'
    )
    $ignoredfiles = @(
        'styles/design-system.css'
    )
    $extensions = @('.php', '.mustache', '.js', '.ts', '.tsx', '.scss', '.css')
    $pattern = '(?<![-_A-Za-z0-9])btn\s+btn-[A-Za-z0-9-]+|(?<![-_A-Za-z0-9])btn-primary\b|(?<![-_A-Za-z0-9])btn-outline-[A-Za-z0-9-]+\b'

    Push-Location $pluginroot
    try {
        $violations = @()
        Get-ChildItem -Recurse -File |
            Where-Object {
                $relative = $_.FullName.Substring($pluginroot.Length).TrimStart('\', '/').Replace('\', '/')
                $extension = $_.Extension.ToLowerInvariant()
                if ($extensions -notcontains $extension) {
                    return $false
                }
                if ($relative -match '(^|/)(vendor|node_modules)/') {
                    return $false
                }
                if ($allowedfiles -contains $relative -or $ignoredfiles -contains $relative) {
                    return $false
                }
                foreach ($root in $ignoredroots) {
                    if ($relative.StartsWith($root, [System.StringComparison]::OrdinalIgnoreCase)) {
                        return $false
                    }
                }
                return $true
            } |
            ForEach-Object {
                $relative = $_.FullName.Substring($pluginroot.Length).TrimStart('\', '/').Replace('\', '/')
                Select-String -Path $_.FullName -Pattern $pattern -AllMatches |
                    ForEach-Object {
                        $line = $_.LineNumber
                        foreach ($match in $_.Matches) {
                            $violations += "${relative}:${line}: $($match.Value)"
                        }
                    }
            }

        if ($violations.Count -gt 0) {
            $violations | ForEach-Object { Write-Error $_ }
            throw 'Legacy Bootstrap button classes are only allowed in branding/compatibility bridge files.'
        }

        Write-Host 'No disallowed legacy Bootstrap button classes found.'
    } finally {
        Pop-Location
    }
}

Assert-Command php
Assert-Command git
Assert-Command phpcs
Assert-Command composer
Assert-Command node

Invoke-NativeStep -Name 'Git whitespace check' -WorkingDirectory $pluginroot -File 'git' -Arguments @('diff', '--check')
Invoke-LegacyButtonGuard
Invoke-NativeStep -Name 'String audit guard' -WorkingDirectory $pluginroot -File 'node' -Arguments @('scripts/check-string-audit.mjs')
try {
    Invoke-NativeStep `
        -Name 'Moodle PHPCS' `
        -WorkingDirectory $pluginroot `
        -File 'phpcs' `
        -Arguments @(
            '--standard=moodle',
            '--extensions=php',
            '--ignore=*/vendor/*,*/node_modules/*,*/.codex-*/*',
            '--report=summary',
            '.'
        )
} catch {
    if (-not $PhpcsReportOnly) {
        throw
    }

    Write-Warning "Moodle PHPCS reported violations: $($_.Exception.Message)"
}
Invoke-PhpLint
Invoke-NativeStep -Name 'Composer audit' -WorkingDirectory $pluginroot -File 'composer' -Arguments @('audit', '--no-interaction')
Invoke-NativeStep `
    -Name 'Composer optimized autoload' `
    -WorkingDirectory $pluginroot `
    -File 'composer' `
    -Arguments @('dump-autoload', '--optimize', '--no-interaction')

if (-not $Fast -and -not $SkipBuild) {
    Assert-Command node
    Assert-Command grunt
    Invoke-NativeStep `
        -Name 'Design system CSS build' `
        -WorkingDirectory $checkoutroot `
        -File 'node' `
        -Arguments @("$pluginrelative/styles/tools/build-design-system.mjs")
    Invoke-NativeStep `
        -Name 'AMD build' `
        -WorkingDirectory $checkoutroot `
        -File 'grunt' `
        -Arguments @('amd', "--root=$pluginrelative")
    Invoke-NativeStep `
        -Name 'React build' `
        -WorkingDirectory $checkoutroot `
        -File 'grunt' `
        -Arguments @('react', "--root=$pluginrelative")
}

if (-not $Fast -and -not $SkipPhpUnit) {
    Invoke-NativeStep `
        -Name 'PHPUnit diagnostics' `
        -WorkingDirectory $checkoutroot `
        -File 'php' `
        -Arguments @('public/admin/tool/phpunit/cli/util.php', '--diag')
    Invoke-NativeStep `
        -Name 'PHPUnit local_moderncommerce suite' `
        -WorkingDirectory $checkoutroot `
        -File 'vendor/bin/phpunit' `
        -Arguments @('-c', 'phpunit.xml', '--testsuite', $phpunitsuite, '--no-coverage')
}

if ($IncludeMustache) {
    Assert-Command moodle-plugin-ci
    Invoke-MustacheLint
}

Write-Host 'Modern Commerce git checks completed.'

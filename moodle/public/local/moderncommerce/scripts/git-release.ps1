[CmdletBinding()]
param(
    [switch]$NoCheck,
    [switch]$NoPackage,
    [switch]$FastCheck,
    [switch]$SkipBuild,
    [switch]$SkipPhpUnit,
    [switch]$Commit,
    [string]$Message = '',
    [switch]$Tag,
    [string]$TagName = '',
    [switch]$Push,
    [switch]$ForcePackage
)

$ErrorActionPreference = 'Stop'

$pluginroot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$checkscript = Join-Path $PSScriptRoot 'git-check.ps1'
$packagescript = Join-Path $PSScriptRoot 'git-package.ps1'
$versionfile = Join-Path $pluginroot 'version.php'

function Get-ReleaseVersion {
    $content = Get-Content -Raw -Path $versionfile
    if ($content -match '\$plugin->release\s*=\s*[''"]([^''"]+)[''"]\s*;') {
        return $Matches[1]
    }

    throw 'Unable to read $plugin->release from version.php.'
}

function Invoke-Git {
    param([string[]]$Arguments)

    Push-Location $pluginroot
    try {
        & git @Arguments
        if ($LASTEXITCODE -ne 0) {
            throw "git $($Arguments -join ' ') failed with exit code $LASTEXITCODE."
        }
    } finally {
        Pop-Location
    }
}

function Get-GitStatus {
    Push-Location $pluginroot
    try {
        return (& git status --short)
    } finally {
        Pop-Location
    }
}

$release = Get-ReleaseVersion
if ($TagName.Trim() -eq '') {
    $TagName = "v$release"
}

if (-not $NoCheck) {
    $checkargs = @()
    if ($FastCheck) {
        $checkargs += '-Fast'
    }
    if ($SkipBuild) {
        $checkargs += '-SkipBuild'
    }
    if ($SkipPhpUnit) {
        $checkargs += '-SkipPhpUnit'
    }
    & $checkscript @checkargs
    if ($LASTEXITCODE -ne 0) {
        throw 'Release checks failed.'
    }
}

if (-not $NoPackage) {
    $packageargs = @('-Version', $release)
    if ($ForcePackage) {
        $packageargs += '-Force'
    }
    & $packagescript @packageargs
    if ($LASTEXITCODE -ne 0) {
        throw 'Release packaging failed.'
    }
}

if ($Commit) {
    if ($Message.Trim() -eq '') {
        throw 'A commit message is required when using -Commit.'
    }
    Invoke-Git @('add', '-A')
    Invoke-Git @('commit', '-m', $Message)
}

if ($Tag) {
    $status = Get-GitStatus
    if ($status.Count -gt 0) {
        throw 'Refusing to tag while the plugin worktree has uncommitted changes. Commit first or omit -Tag.'
    }

    Push-Location $pluginroot
    try {
        $existing = & git tag --list $TagName
        if ($existing) {
            throw "Tag already exists: $TagName"
        }
    } finally {
        Pop-Location
    }

    Invoke-Git @('tag', '-a', $TagName, '-m', "Modern Commerce $TagName")
}

if ($Push) {
    Invoke-Git @('push')
    if ($Tag) {
        Invoke-Git @('push', 'origin', $TagName)
    }
}

Write-Host "Modern Commerce release automation finished for $release."

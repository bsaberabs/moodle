[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$pluginroot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$hookspath = Join-Path $pluginroot '.githooks'

if (-not (Test-Path $hookspath)) {
    throw "Hook directory does not exist: $hookspath"
}

Push-Location $pluginroot
try {
    git config core.hooksPath .githooks
    if ($LASTEXITCODE -ne 0) {
        throw 'Unable to configure git hooksPath.'
    }
    Write-Host 'Git hooks installed for local_moderncommerce.'
    Write-Host 'Configured core.hooksPath=.githooks'
} finally {
    Pop-Location
}

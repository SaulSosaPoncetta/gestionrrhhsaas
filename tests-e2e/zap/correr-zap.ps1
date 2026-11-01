<#
.SYNOPSIS
  Corre OWASP ZAP contra el Sistema de Gestión RRHH (local o staging, NUNCA producción).

.EJEMPLOS
  # Escaneo base (pasivo, sin login, ~2 minutos) con Docker
  .\correr-zap.ps1 -Modo base

  # Escaneo completo autenticado (spider + ataques, 30 a 60 minutos) con Docker
  .\correr-zap.ps1 -Modo completo

  # Lo mismo con ZAP Desktop instalado (sin Docker)
  .\correr-zap.ps1 -Modo completo -Motor desktop

  # Contra un staging
  .\correr-zap.ps1 -Modo completo -Url https://staging.tu-dominio.com.ar
#>
param(
    [ValidateSet('base', 'completo')] [string] $Modo = 'base',
    [ValidateSet('docker', 'desktop')] [string] $Motor = 'docker',
    [string] $Url = '',
    [string] $ZapDesktop = 'C:\Program Files\ZAP\Zed Attack Proxy\zap.bat'
)

$ErrorActionPreference = 'Stop'
$aqui = $PSScriptRoot
New-Item -ItemType Directory -Force -Path "$aqui\reporte" | Out-Null

# Desde Docker, "localhost" es el propio contenedor: a tu PC se llega con host.docker.internal
if (-not $Url) {
    $Url = if ($Motor -eq 'docker') { 'http://host.docker.internal:8000' } else { 'http://127.0.0.1:8000' }
}
$Url = $Url.TrimEnd('/')

if ($Url -match 'migestion\.com\.ar' -and $Url -notmatch 'staging|test|prueba') {
    Write-Host "Parece una URL de produccion ($Url). ZAP no se corre contra produccion." -ForegroundColor Red
    exit 1
}

Write-Host "ZAP $Modo con $Motor contra $Url" -ForegroundColor Cyan
$imagen = 'ghcr.io/zaproxy/zaproxy:stable'

# zap.bat busca zap-<version>.jar en la carpeta actual: hay que ejecutarlo parado en la carpeta de ZAP
function Invoke-ZapDesktop([string] $plan) {
    if (-not (Test-Path $ZapDesktop)) {
        Write-Host "No encuentro ZAP en $ZapDesktop. Indicá la ruta con -ZapDesktop 'C:\...\zap.bat'" -ForegroundColor Red
        exit 1
    }
    $env:ZAP_TARGET = $Url
    $env:ZAP_REPORTES = "$aqui\reporte"
    Push-Location (Split-Path $ZapDesktop -Parent)
    try {
        & $ZapDesktop -cmd -autorun $plan
        $script:codigo = $LASTEXITCODE
    } finally {
        Pop-Location
    }
}

$codigo = 0

if ($Modo -eq 'base') {
    if ($Motor -eq 'docker') {
        docker run --rm -v "${aqui}:/zap/wrk/:rw" -t $imagen `
            zap-baseline.py -t $Url -c reglas-baseline.conf `
            -r reporte/reporte_zap_base.html -J reporte/reporte_zap_base.json
        $codigo = $LASTEXITCODE
    } else {
        Invoke-ZapDesktop "$aqui\zap-plan-base.yaml"
    }
} else {
    if ($Motor -eq 'docker') {
        docker run --rm -v "${aqui}:/zap/wrk/:rw" `
            -e ZAP_TARGET=$Url -e ZAP_REPORTES=/zap/wrk/reporte `
            -t $imagen zap.sh -cmd -autorun /zap/wrk/zap-plan.yaml
        $codigo = $LASTEXITCODE
    } else {
        Invoke-ZapDesktop "$aqui\zap-plan.yaml"
    }
}

$reportes = Get-ChildItem "$aqui\reporte" -File -ErrorAction SilentlyContinue
if (-not $reportes) {
    Write-Host "`nZAP no genero reportes (codigo $codigo). Revisa los mensajes de arriba." -ForegroundColor Red
    exit 1
}

# zap-baseline devuelve 1 si hay alertas FAIL y 2 si hay WARN: igual se generan los reportes
Write-Host "`nReportes en $aqui\reporte (codigo de salida de ZAP: $codigo)" -ForegroundColor Green
$reportes | Select-Object Name, Length, LastWriteTime | Format-Table

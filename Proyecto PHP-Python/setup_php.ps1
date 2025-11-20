# <#
# Script: setup_php.ps1
# Objetivo: Detectar/instalar PHP en Windows, habilitar extensiones sqlite, iniciar servidor embebido y probar la base.
# Uso:
#   1) Abrir PowerShell como usuario normal (no estrictamente admin salvo instalación).
#   2) Ejecutar:  powershell -ExecutionPolicy Bypass -File .\setup_php.ps1
# #>

param(
  [string]$ProjectPublicPath = "d:\Users\Johan.hernandez\OneDrive - WinSports SAS\Escritorio\Proyecto PHP-Python\Public",
  [string]$Port = "8000",
  [switch]$UseMySQL,
  [Security.SecureString]$MySQLRootPasswordSecure,
  [string]$MySQLDatabase = "winsports",
  [switch]$RestartServer
)

Write-Host "=== Diagnóstico inicial PHP ===" -ForegroundColor Cyan
$phpPaths = @(Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source) | Where-Object { $_ }
if (-not $phpPaths) {
  Write-Warning "php.exe no encontrado en PATH. Intentando instalación con winget..."
  $winget = Get-Command winget -ErrorAction SilentlyContinue
  if ($winget) {
    Write-Host "Ejecutando: winget install --id=PHP.PHP -e" -ForegroundColor Yellow
    winget install --id=PHP.PHP -e | Out-Null
    $phpPaths = @(Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source) | Where-Object { $_ }
  } else {
    Write-Error "Winget no disponible. Instala manualmente desde https://windows.php.net/download/ (ZIP Thread Safe) y ponlo en C:\php. Luego agrega C:\php al PATH."
    exit 1
  }
}

if (-not $phpPaths) { Write-Error "php.exe sigue sin encontrarse tras instalación."; exit 1 }
$phpExe = $phpPaths | Select-Object -First 1
Write-Host "PHP detectado en: $phpExe" -ForegroundColor Green

# Localizar carpeta base de PHP
$phpDir = Split-Path $phpExe -Parent
$iniDev = Join-Path $phpDir 'php.ini-development'
$iniProd = Join-Path $phpDir 'php.ini-production'
$ini    = Join-Path $phpDir 'php.ini'

if (-not (Test-Path $ini)) {
  if (Test-Path $iniDev) { Copy-Item $iniDev $ini } elseif (Test-Path $iniProd) { Copy-Item $iniProd $ini }
  Write-Host "Creado php.ini desde plantilla" -ForegroundColor Yellow
}

if (-not (Test-Path $ini)) { Write-Error "No se pudo crear php.ini"; exit 1 }

Write-Host "Configurando extensiones en php.ini" -ForegroundColor Cyan
$iniContent = Get-Content $ini -Raw
function Enable-Line($content, $pattern) {
  return ($content -replace "(?m)^;(?=\s*$pattern)", "")
}
$iniContent = Enable-Line $iniContent 'extension_dir'
foreach ($ext in 'extension=pdo_sqlite','extension=sqlite3') {
  if ($iniContent -notmatch [regex]::Escape($ext)) {
    $iniContent += "`r`n$ext"
  } else {
    $iniContent = ($iniContent -replace "(?m)^;($ext)", "$1")
  }
}
if ($UseMySQL) {
  foreach ($ext in 'extension=mysqli','extension=pdo_mysql') {
    if ($iniContent -notmatch [regex]::Escape($ext)) {
      $iniContent += "`r`n$ext"
    } else {
      $iniContent = ($iniContent -replace "(?m)^;($ext)", "$1")
    }
  }
}
if ($iniContent -notmatch 'date.timezone') { $iniContent += "`r`ndate.timezone = America/Bogota" }
Set-Content -Path $ini -Value $iniContent -Encoding UTF8
Write-Host "php.ini actualizado." -ForegroundColor Green

Write-Host "Verificando módulos" -ForegroundColor Cyan
php -m | Select-String -Pattern 'pdo_sqlite','sqlite3','pdo_mysql','mysqli' | ForEach-Object { $_.Line } | ForEach-Object { Write-Host "  Modulo: $_" -ForegroundColor Green }

if ($UseMySQL) {
  Write-Host "=== Modo MySQL/MariaDB activado ===" -ForegroundColor Cyan
  $maria = Get-Command mariadb -ErrorAction SilentlyContinue
  if (-not $maria) {
    $winget = Get-Command winget -ErrorAction SilentlyContinue
    if ($winget) {
      Write-Host "Instalando MariaDB via winget..." -ForegroundColor Yellow
      winget install --id=MariaDB.MariaDB -e | Out-Null
      $maria = Get-Command mariadb -ErrorAction SilentlyContinue
    } else {
      Write-Warning "Winget no disponible: instala MariaDB/MySQL manualmente y re-ejecuta con -UseMySQL"
    }
  }
  if ($maria) {
    Write-Host "MariaDB detectado: $($maria.Source)" -ForegroundColor Green
    # Intentar crear DB y usuario root si es necesario
    # Preparar argumento de password si se proporcionó seguro
    $rootPassPlain = $null
    if ($MySQLRootPasswordSecure) {
      try {
        $ptr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($MySQLRootPasswordSecure)
        $rootPassPlain = [System.Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
      } catch { Write-Warning "No se pudo convertir SecureString: $_" }
    }
    $baseArgs = @('-u','root')
    if ($rootPassPlain) { $baseArgs += "-p$rootPassPlain" }
    try {
      mariadb @baseArgs -e "CREATE DATABASE IF NOT EXISTS \`$MySQLDatabase\`;" 2>$null
    } catch { Write-Warning "Error creando base de datos (verifique contraseña root)." }
    Write-Host "Base de datos '$MySQLDatabase' verificada/creada." -ForegroundColor Green
    # Ajustar config.php si existe
    $configPath = Join-Path $ProjectPublicPath 'PHP\config.php'
    if (Test-Path $configPath) {
      $cfg = Get-Content $configPath -Raw
      $cfg = ($cfg -replace "'DB_DRIVER'\s*=>\s*'sqlite'","'DB_DRIVER'  => 'mysql'")
      $cfg = ($cfg -replace "'DB_NAME'\s*=>\s*'.*'","'DB_NAME'    => '$MySQLDatabase'")
      Set-Content -Path $configPath -Value $cfg -Encoding UTF8
      Write-Host "config.php actualizado a driver mysql." -ForegroundColor Yellow
    } else {
      Write-Warning "config.php no encontrado para actualizar driver."
    }
    # Importar inventario.mysql.sql si la tabla no existe
    $sqlFile = Join-Path (Split-Path $ProjectPublicPath -Parent) 'SQL\inventario.mysql.sql'
    if (Test-Path $sqlFile) {
      Write-Host "Importando inventario.mysql.sql..." -ForegroundColor Cyan
      try {
        if ($rootPassPlain) {
          Get-Content -Raw $sqlFile | mariadb @baseArgs $MySQLDatabase
        } else {
          Get-Content -Raw $sqlFile | mariadb -u root $MySQLDatabase
        }
        Write-Host "Importación MySQL completa." -ForegroundColor Green
      }
      catch { Write-Warning "Fallo importando inventario.mysql.sql: $_" }
    } else { Write-Warning "Archivo SQL MySQL no encontrado: $sqlFile" }
  }
}

if (-not (Test-Path $ProjectPublicPath)) { Write-Error "Ruta Public no existe: $ProjectPublicPath"; exit 1 }

Write-Host "Preparando servidor embebido en puerto $Port..." -ForegroundColor Cyan
if ($RestartServer) {
  Write-Host "Reinicio solicitado: buscando procesos php en localhost:$Port" -ForegroundColor Yellow
  try {
    $phpProcs = Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -like "*localhost:$Port*" }
    foreach ($p in $phpProcs) {
      Write-Host "Deteniendo php PID=$($p.ProcessId)" -ForegroundColor Red
      Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue
    }
  } catch { Write-Warning "No se pudo enumerar procesos PHP: $_" }
  # Limpiar jobs huérfanos (opcionales)
  Get-Job | Where-Object { $_.State -in 'Running','Starting' } | Remove-Job -Force -ErrorAction SilentlyContinue
}

$serverCmd = "php -S localhost:$Port -t `"$ProjectPublicPath`""
Write-Host "Comando servidor: $serverCmd" -ForegroundColor Yellow
Start-Job -ScriptBlock { param($c) & powershell -NoLogo -Command $c } -ArgumentList $serverCmd | Out-Null
Start-Sleep -Seconds 3

Write-Host "Probando endpoint de estado: /PHP/db_status.php" -ForegroundColor Cyan
try {
  $statusJson = Invoke-WebRequest -UseBasicParsing -Uri "http://localhost:$Port/PHP/db_status.php" -Method GET -TimeoutSec 10
  $body = $statusJson.Content
  Write-Host "Respuesta db_status: $body" -ForegroundColor Green
} catch {
  Write-Warning "No se pudo obtener db_status: $_"
}

Write-Host "Si la tabla no existe o filas = 0, importa SQL:" -ForegroundColor Yellow
Write-Host "curl -X POST http://localhost:$Port/PHP/import_sql.php" -ForegroundColor Yellow
Write-Host "Luego abre: http://localhost:$Port/Views/Bases_de_Datos/Base_de_Datos.html" -ForegroundColor Cyan

Write-Host "Script finalizado." -ForegroundColor Magenta
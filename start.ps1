param([switch]$NoBrowser)
$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$mariaRoot = Join-Path $root '.tools\mariadb\mariadb-11.4.0-winx64'
$mariaExe = Join-Path $mariaRoot 'bin\mariadbd.exe'
$phpExe = Join-Path $root '.tools\php\php.exe'

if (-not (Test-Path -LiteralPath $mariaExe) -or -not (Test-Path -LiteralPath $phpExe)) {
    throw 'Les outils locaux sont absents. Consultez le README pour installer les dépendances.'
}

function Test-LocalPort([int]$Port) {
    $client = [System.Net.Sockets.TcpClient]::new()
    try {
        $connection = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        if (-not $connection.AsyncWaitHandle.WaitOne(200)) { return $false }
        $client.EndConnect($connection)
        return $true
    }
    catch { return $false }
    finally { $client.Dispose() }
}

if (-not (Test-LocalPort 3307)) {
    Start-Process -FilePath $mariaExe -WorkingDirectory $mariaRoot `
        -ArgumentList '--defaults-file=.\testdata\my.ini','--port=3307','--skip-networking=0','--bind-address=127.0.0.1' `
        -WindowStyle Hidden
}

$ready = $false
for ($attempt = 0; $attempt -lt 20; $attempt++) {
    if (Test-LocalPort 3307) { $ready = $true; break }
    Start-Sleep -Milliseconds 250
}
if (-not $ready) { throw 'MariaDB ne répond pas sur le port 3307.' }

if (-not (Test-LocalPort 8080)) {
    Start-Process -FilePath $phpExe -WorkingDirectory $root `
        -ArgumentList '-d','extension_dir=.tools/php/ext','-d','extension=pdo_mysql','-S','127.0.0.1:8080','-t','public' `
        -RedirectStandardOutput (Join-Path $root '.tools\php-server.out') `
        -RedirectStandardError (Join-Path $root '.tools\php-server.err') `
        -WindowStyle Hidden
}

$ready = $false
for ($attempt = 0; $attempt -lt 20; $attempt++) {
    if (Test-LocalPort 8080) { $ready = $true; break }
    Start-Sleep -Milliseconds 250
}
if (-not $ready) { throw 'PHP ne répond pas sur le port 8080.' }

if (-not $NoBrowser) { Start-Process 'http://127.0.0.1:8080/' }
Write-Host 'Muscu est demarre : http://127.0.0.1:8080/' -ForegroundColor Green
Write-Host 'Pour arreter les serveurs : .\stop.ps1'

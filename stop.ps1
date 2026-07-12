$root = $PSScriptRoot
$tools = Join-Path $root '.tools'
$stopped = 0

Get-Process php,mariadbd -ErrorAction SilentlyContinue |
    Where-Object { $_.Path -and $_.Path.StartsWith($tools, [System.StringComparison]::OrdinalIgnoreCase) } |
    ForEach-Object { Stop-Process -Id $_.Id -Force; $stopped++ }

if ($stopped -gt 0) {
    Write-Host "Muscu arrete ($stopped processus)." -ForegroundColor Green
} else {
    Write-Host 'Muscu etait deja arrete.'
}

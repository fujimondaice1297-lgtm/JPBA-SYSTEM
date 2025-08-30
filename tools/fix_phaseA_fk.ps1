# tools\fix_phaseA_fk.ps1
$ErrorActionPreference = 'Stop'

# Phase A = 2025_09_01_* の CREATE から FK を撤去し「列だけ」にする
$files = Get-ChildItem -Path "database\migrations" -Filter "2025_09_01_*.php" -File
Write-Host "Scanning $($files.Count) files..."

foreach ($f in $files) {
  $t = Get-Content -LiteralPath $f.FullName -Raw

  # 1) foreign('col')->references('id')->on('table')...[修飾子] ;  → foreignId('col');
  $t = $t -replace "\$table->foreign\('([^']+)'\)\s*->references\('id'\)\s*->on\('([^']+)'\)\s*(?:->\w+\([^)]*\))*\s*;", '$table->foreignId(''$1'');'

  # 2) foreignId(...)->constrained('table')...[修飾子] ;  → foreignId(...)
  $t = $t -replace "->constrained\('.*?'\)\s*(?:->\w+\([^)]*\))*\s*;", ";"
  $t = $t -replace "->constrained\(\)\s*(?:->\w+\([^)]*\))*\s*;", ";"

  # 3) 残ってる修飾子（cascade/nullOnDelete等）を除去
  $t = $t -replace "->cascadeOnDelete\(\)", ""
  $t = $t -replace "->cascadeOnUpdate\(\)", ""
  $t = $t -replace "->restrictOnDelete\(\)", ""
  $t = $t -replace "->nullOnDelete\(\)", ""
  $t = $t -replace "->onDelete\('.*?'\)", ""
  $t = $t -replace "->onUpdate\('.*?'\)", ""

  # 4) ライセンスNoをFKにしていた箇所はいったん文字列列に
  $t = $t -replace "\$table->foreign\('license_no'\)[^;]*;", "$table->string('license_no');"
  $t = $t -replace "\$table->foreign\('pro_bowler_license_no'\)[^;]*;", "$table->string('pro_bowler_license_no');"

  Set-Content -LiteralPath $f.FullName -Value $t -Encoding UTF8
  Write-Host "Fixed: $($f.Name)"
}

# 残存チェック
$left = Select-String -Path "database\migrations\2025_09_01_*.php" -Pattern "->constrained|->foreign\(" -List
if ($left) {
  Write-Warning "FK-like syntax still found in Phase A files:"
  $left | ForEach-Object { Write-Host " - $($_.Path):$($_.LineNumber): $($_.Line.Trim())" }
} else {
  Write-Host "Phase A FK removal looks clean."
}

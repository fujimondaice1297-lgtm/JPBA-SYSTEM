# tools\fix_phaseA_fk_cp932.ps1
$ErrorActionPreference = 'Stop'
$srcEnc = [System.Text.Encoding]::GetEncoding(932)        # CP932 (Shift-JIS)
$dstEnc = New-Object System.Text.UTF8Encoding $true       # UTF-8 with BOM

$targets = Get-ChildItem "database\migrations\2025_09_01_*.php" -File
Write-Host "Fixing FK in $($targets.Count) Phase-A files..."

foreach ($f in $targets) {
  # 1) まずバイトで読み、UTF-8として読めなそうならCP932として再解釈
  $bytes = [IO.File]::ReadAllBytes($f.FullName)
  $text  = [Text.Encoding]::UTF8.GetString($bytes)
  if ($text -notmatch '^\s*<\?php' -or $text.Contains("?")) {
    $text = $srcEnc.GetString($bytes)
  }

  # 2) CREATE内の外部キー連鎖を「列だけ」に置換
  $text = $text -replace "\$table->foreign\('([^']+)'\)\s*->references\('id'\)\s*->on\('([^']+)'\)\s*(?:->\w+\([^)]*\))*\s*;", '$table->foreignId(''$1'');'
  $text = $text -replace "->constrained\('.*?'\)\s*(?:->\w+\([^)]*\))*\s*;", ";"
  $text = $text -replace "->constrained\(\)\s*(?:->\w+\([^)]*\))*\s*;", ";"
  $text = $text -replace "->cascadeOnDelete\(\)|->cascadeOnUpdate\(\)|->restrictOnDelete\(\)|->nullOnDelete\(\)|->onDelete\('.*?'\)|->onUpdate\('.*?'\)", ""

  # 3) license_no をFKにしていたら一旦プレーン列へ
  $text = $text -replace "\$table->foreign\('license_no'\)[^;]*;", "$table->string('license_no');"
  $text = $text -replace "\$table->foreign\('pro_bowler_license_no'\)[^;]*;", "$table->string('pro_bowler_license_no');"

  # 4) 先頭タグが壊れてたら矯正（k?php 等）
  if ($text -notmatch '^\s*<\?php') {
    $text = ($text -replace '^\s*.+\?php', '<?php')  # 先頭の壊れタグを '<?php' に
  }

  [IO.File]::WriteAllText($f.FullName, $text, $dstEnc)
  Write-Host "Fixed: $($f.Name)"
}

# 残存チェック
$left = Select-String -Path "database\migrations\2025_09_01_*.php" -Pattern "->constrained|->foreign\(" -List
if ($left) {
  Write-Warning "Still found FK-like syntax:"
  $left | % { Write-Host " - $($_.Path):$($_.LineNumber): $($_.Line.Trim())" }
} else {
  Write-Host "Phase A FK removal looks clean (UTF-8 normalized)."
}

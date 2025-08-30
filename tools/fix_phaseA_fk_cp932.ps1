# tools\fix_phaseA_fk_cp932.ps1
$ErrorActionPreference = 'Stop'
$srcEnc = [System.Text.Encoding]::GetEncoding(932)        # CP932 (Shift-JIS)
$dstEnc = New-Object System.Text.UTF8Encoding $true       # UTF-8 with BOM

$targets = Get-ChildItem "database\migrations\2025_09_01_*.php" -File
Write-Host "Fixing FK in $($targets.Count) Phase-A files..."

foreach ($f in $targets) {
  # 1) �܂��o�C�g�œǂ݁AUTF-8�Ƃ��ēǂ߂Ȃ����Ȃ�CP932�Ƃ��čĉ���
  $bytes = [IO.File]::ReadAllBytes($f.FullName)
  $text  = [Text.Encoding]::UTF8.GetString($bytes)
  if ($text -notmatch '^\s*<\?php' -or $text.Contains("?")) {
    $text = $srcEnc.GetString($bytes)
  }

  # 2) CREATE���̊O���L�[�A�����u�񂾂��v�ɒu��
  $text = $text -replace "\$table->foreign\('([^']+)'\)\s*->references\('id'\)\s*->on\('([^']+)'\)\s*(?:->\w+\([^)]*\))*\s*;", '$table->foreignId(''$1'');'
  $text = $text -replace "->constrained\('.*?'\)\s*(?:->\w+\([^)]*\))*\s*;", ";"
  $text = $text -replace "->constrained\(\)\s*(?:->\w+\([^)]*\))*\s*;", ";"
  $text = $text -replace "->cascadeOnDelete\(\)|->cascadeOnUpdate\(\)|->restrictOnDelete\(\)|->nullOnDelete\(\)|->onDelete\('.*?'\)|->onUpdate\('.*?'\)", ""

  # 3) license_no ��FK�ɂ��Ă������U�v���[�����
  $text = $text -replace "\$table->foreign\('license_no'\)[^;]*;", "$table->string('license_no');"
  $text = $text -replace "\$table->foreign\('pro_bowler_license_no'\)[^;]*;", "$table->string('pro_bowler_license_no');"

  # 4) �擪�^�O�����Ă��狸���ik?php ���j
  if ($text -notmatch '^\s*<\?php') {
    $text = ($text -replace '^\s*.+\?php', '<?php')  # �擪�̉��^�O�� '<?php' ��
  }

  [IO.File]::WriteAllText($f.FullName, $text, $dstEnc)
  Write-Host "Fixed: $($f.Name)"
}

# �c���`�F�b�N
$left = Select-String -Path "database\migrations\2025_09_01_*.php" -Pattern "->constrained|->foreign\(" -List
if ($left) {
  Write-Warning "Still found FK-like syntax:"
  $left | % { Write-Host " - $($_.Path):$($_.LineNumber): $($_.Line.Trim())" }
} else {
  Write-Host "Phase A FK removal looks clean (UTF-8 normalized)."
}

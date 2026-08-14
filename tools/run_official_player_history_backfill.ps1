param(
    [int] $MaxAttempts = 48,
    [int] $RetryMinutes = 30,
    [int] $SleepMs = 500,
    [string] $LogPath = ''
)

$taskProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$taskPhpPath = (Get-Command php -ErrorAction Stop).Source

if ([string]::IsNullOrWhiteSpace($LogPath)) {
    $LogPath = Join-Path $taskProjectRoot 'storage\logs\official_player_history_backfill.log'
}

$taskLogDirectory = Split-Path -Parent $LogPath
New-Item -ItemType Directory -Force -Path $taskLogDirectory | Out-Null
Set-Location $taskProjectRoot

for ($taskAttempt = 1; $taskAttempt -le $MaxAttempts; $taskAttempt++) {
    $taskStartedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    "[$taskStartedAt] attempt $taskAttempt of $MaxAttempts" |
        Tee-Object -FilePath $LogPath -Append

    $taskArguments = @(
        'artisan',
        'jpba:import-official-player-profile-stats',
        '--all-visible',
        '--snapshot-existing-only',
        '--with-history',
        '--history-pending-only',
        '--history-missing-only',
        '--history-concurrency=1',
        '--force',
        "--sleep-ms=$SleepMs",
        '--json'
    )

    & $taskPhpPath @taskArguments 2>&1 |
        Tee-Object -FilePath $LogPath -Append
    $taskExitCode = $LASTEXITCODE

    if ($taskExitCode -eq 0) {
        $taskFinishedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
        "[$taskFinishedAt] completed" |
            Tee-Object -FilePath $LogPath -Append
        exit 0
    }

    if ($taskAttempt -lt $MaxAttempts) {
        $taskRetryAt = (Get-Date).AddMinutes($RetryMinutes).ToString('yyyy-MM-dd HH:mm:ss')
        "[$taskStartedAt] stopped safely; retry at $taskRetryAt" |
            Tee-Object -FilePath $LogPath -Append
        Start-Sleep -Seconds ($RetryMinutes * 60)
    }
}

$taskStoppedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
"[$taskStoppedAt] retry limit reached" |
    Tee-Object -FilePath $LogPath -Append
exit 1

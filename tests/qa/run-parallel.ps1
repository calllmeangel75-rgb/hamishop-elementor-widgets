# QA: run N wallet operations as separate OS processes at the same instant.
# usage: .\run-parallel.ps1 -Scenario debit -User 43 -Amount 300 -N 30
param(
	[string]$Scenario = 'debit',
	[int]$User = 0,
	[double]$Amount = 300,
	[int]$N = 30,
	[int]$Order = 0,
	[string]$Mode = 'direct'
)

$php  = 'C:\xampp\php\php.exe'
$root = 'D:\work-webs\0 Build\hamishop-repo'
$tmp  = Join-Path $root 'tests\qa\out'
if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
New-Item -ItemType Directory -Path $tmp | Out-Null

# Shared start: far enough ahead for 60 boots, close enough to stay in one window.
$start = [double]([DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds() / 1000.0) + 6.0
$startStr = $start.ToString([System.Globalization.CultureInfo]::InvariantCulture)

$procs = @()
$mixed = @('direct', 'payment_complete', 'status_processing', 'status_completed')
for ($i = 0; $i -lt $N; $i++) {
	if ($Scenario -eq 'settle') {
		# -ArgumentList joins array items with spaces without quoting, and the repo
		# path contains a space ("0 Build"), so the script path must be quoted here.
		$mode = if ($Mode -eq 'mixed') { $mixed[$i % $mixed.Count] } else { $Mode }
		$script = "$root\tests\qa\worker-settle.php"
		$argLine = '"' + $script + '" ' + "$Order $mode $startStr"
	} else {
		$script = "$root\tests\qa\worker-wallet.php"
		$argLine = '"' + $script + '" ' + "$Scenario $User $Amount $startStr"
	}
	$outFile = Join-Path $tmp ("w{0}.json" -f $i)
	$procs += Start-Process -FilePath $php -ArgumentList $argLine -RedirectStandardOutput $outFile -RedirectStandardError (Join-Path $tmp ("e{0}.txt" -f $i)) -NoNewWindow -PassThru
}

$procs | Wait-Process -Timeout 120

$lines = Get-ChildItem $tmp -Filter 'w*.json' | ForEach-Object { Get-Content $_.FullName } | Where-Object { $_ -match '^\{' }
$ok = 0; $err = 0; $codes = @{}
foreach ($l in $lines) {
	$o = $l | ConvertFrom-Json
	if ($o.ok) { $ok++ } else { $err++; $codes[$o.code] = 1 + ($codes[$o.code] | ForEach-Object { $_ }) }
}
$errFiles = Get-ChildItem $tmp -Filter 'e*.txt' | Where-Object { $_.Length -gt 0 } | Measure-Object
"scenario=$Scenario n=$N started=$startStr"
"responses=$($lines.Count) ok=$ok err=$err"
($codes.GetEnumerator() | ForEach-Object { "error {$($_.Key)} x$($_.Value)" })
if ($errFiles.Count -gt 0) { "stderr files with content: $($errFiles.Count)" }

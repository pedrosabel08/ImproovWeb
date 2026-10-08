param([switch]$ResumeValidatedConfig)
$ErrorActionPreference = 'Stop'
$privateRoot = 'C:/ProgramData/ImproovWeb/private'
$confPath = "$privateRoot/apache/pagamento-fechamento.conf"
$activePath = 'C:/xampp/apache/conf/httpd.conf'
$evidencePath = 'C:/xampp/htdocs/ImproovWeb/docs/evidence/pagamento-deployment-apache-2026-10-05.json'
if ((Test-Path -LiteralPath $confPath) -and -not $ResumeValidatedConfig) { throw 'Configuracao privada ja existe: nao sobrescrever' }
if (Test-Path -LiteralPath $evidencePath) { throw 'Evidencia ja existe: nao repetir reload' }
foreach ($stage in @('revisao','documento')) {
    $proof = Get-Content "C:/xampp/htdocs/ImproovWeb/docs/evidence/pagamento-deployment-$stage-2026-10-05.json" -Raw | ConvertFrom-Json
    if ($proof.result -ne 'OK') { throw 'Migration nao validada' }
}
$apachePid = [int](Get-Content 'C:/xampp/apache/logs/httpd.pid')
$parent = Get-CimInstance Win32_Process -Filter "ProcessId=$apachePid"
if ($parent.Name -ne 'httpd.exe' -or $parent.ExecutablePath.ToLower() -ne 'c:\xampp\apache\bin\httpd.exe') { throw 'Instancia Apache inesperada' }
if (@(Get-CimInstance Win32_Service | Where-Object { $_.ProcessId -eq $apachePid }).Count) { throw 'Apache e servico: parar' }
$owner = Invoke-CimMethod -InputObject $parent -MethodName GetOwner
if ("$($owner.Domain)\$($owner.User)" -ne 'IMP-PC011\usuario') { throw 'Identidade Apache mudou' }
$event = [Threading.EventWaitHandle]::OpenExisting("ap${apachePid}_restart",[Security.AccessControl.EventWaitHandleRights]::Modify)
$oldChildren = @(Get-CimInstance Win32_Process -Filter "Name='httpd.exe'" | Where-Object ParentProcessId -eq $apachePid | ForEach-Object ProcessId)
if (-not $ResumeValidatedConfig) {
$original = [IO.File]::ReadAllBytes($activePath)
$activeText = [Text.Encoding]::UTF8.GetString($original)
if ($activeText -match 'PAGAMENTO_FECHAMENTO|pagamento-fechamento.conf') { throw 'Configuracao preexistente: auditar' }
$backupPath = "$privateRoot/deployment-backups/httpd.pre_pagamento_$(Get-Date -Format yyyyMMdd_HHmmss)_$([guid]::NewGuid().ToString('N').Substring(0,8)).conf"
[IO.File]::WriteAllBytes($backupPath,$original)
$dir = [IO.DirectoryInfo]::new("$privateRoot/apache")
$acl = $dir.GetAccessControl([Security.AccessControl.AccessControlSections]::Access)
$savedAcl = $acl.GetSecurityDescriptorSddlForm([Security.AccessControl.AccessControlSections]::Access)
$sid = [Security.Principal.WindowsIdentity]::GetCurrent().User
if ($sid.Translate([Security.Principal.NTAccount]).Value -ne 'IMP-PC011\usuario') { throw 'Executar com identidade autorizada' }
$acl.SetAccessRule([Security.AccessControl.FileSystemAccessRule]::new($sid,'Modify','ContainerInherit, ObjectInherit','None','Allow'))
$dir.SetAccessControl($acl)
try {
    $privateConfig = @'
# Deployment controlado: flag permanece OFF ate autorizacao do canary.
<Directory "C:/xampp/htdocs/ImproovWeb">
    SetEnv PAGAMENTO_FECHAMENTO_V2_ENABLED 0
    SetEnv PAGAMENTO_FECHAMENTO_STORAGE_ROOT "C:/ProgramData/ImproovWeb/private/pagamento-fechamento"
</Directory>
'@
    [IO.File]::WriteAllText($confPath,$privateConfig+"`r`n",[Text.UTF8Encoding]::new($false))
} finally {
    $restoreAcl = $dir.GetAccessControl([Security.AccessControl.AccessControlSections]::Access)
    $restoreAcl.SetSecurityDescriptorSddlForm($savedAcl,[Security.AccessControl.AccessControlSections]::Access)
    $dir.SetAccessControl($restoreAcl)
}
$append = "`r`n# Pagamento / Adendos: configuracao privada server-side`r`nInclude `"C:/ProgramData/ImproovWeb/private/apache/pagamento-fechamento.conf`"`r`n"
$stream = [IO.File]::Open($activePath,[IO.FileMode]::Append,[IO.FileAccess]::Write)
try { $bytes = [Text.Encoding]::ASCII.GetBytes($append); $stream.Write($bytes,0,$bytes.Length) } finally { $stream.Dispose() }
} else {
    $backupPath = "$privateRoot/deployment-backups/httpd.pre_pagamento_20261005_204814_a633438b.conf"
    $expectedConfig = "# Deployment controlado: flag permanece OFF ate autorizacao do canary.`n<Directory `"C:/xampp/htdocs/ImproovWeb`">`n    SetEnv PAGAMENTO_FECHAMENTO_V2_ENABLED 0`n    SetEnv PAGAMENTO_FECHAMENTO_STORAGE_ROOT `"C:/ProgramData/ImproovWeb/private/pagamento-fechamento`"`n</Directory>"
    if (([IO.File]::ReadAllText($confPath) -replace "`r`n","`n").Trim() -ne $expectedConfig) { throw 'Configuracao privada diverge: parar' }
    $append = "`r`n# Pagamento / Adendos: configuracao privada server-side`r`nInclude `"C:/ProgramData/ImproovWeb/private/apache/pagamento-fechamento.conf`"`r`n"
    $expectedActive = [Convert]::ToBase64String([IO.File]::ReadAllBytes($backupPath)+[Text.Encoding]::ASCII.GetBytes($append))
    if ([Convert]::ToBase64String([IO.File]::ReadAllBytes($activePath)) -ne $expectedActive) { throw 'Configuracao Apache diverge: parar' }
}
$syntaxProcess = [Diagnostics.Process]::new()
$syntaxProcess.StartInfo = [Diagnostics.ProcessStartInfo]::new('C:/xampp/apache/bin/httpd.exe','-t -f C:/xampp/apache/conf/httpd.conf')
$syntaxProcess.StartInfo.UseShellExecute = $false
$syntaxProcess.StartInfo.CreateNoWindow = $true
$syntaxProcess.StartInfo.RedirectStandardError = $true
$syntaxProcess.StartInfo.RedirectStandardOutput = $true
[void]$syntaxProcess.Start()
$syntax = $syntaxProcess.StandardError.ReadToEnd() + $syntaxProcess.StandardOutput.ReadToEnd()
$syntaxProcess.WaitForExit()
if ($syntaxProcess.ExitCode -ne 0 -or $syntax -notmatch 'Syntax OK') { throw "httpd -t falhou: $syntax" }
$syntaxProcess.Dispose()
$reloadAt = Get-Date
try { if (-not $event.Set()) { throw 'Sinal de reload falhou' } } finally { $event.Dispose() }
$newChildren = @()
for ($attempt=0; $attempt -lt 30; $attempt++) {
    Start-Sleep -Milliseconds 500
    $newChildren = @(Get-CimInstance Win32_Process -Filter "Name='httpd.exe'" | Where-Object { $_.ParentProcessId -eq $apachePid -and $_.ProcessId -notin $oldChildren } | ForEach-Object ProcessId)
    if ($newChildren.Count -gt 0) { break }
}
if (-not $newChildren.Count) { throw 'Reload sem novo child verificado: parar' }
$fileAcl = Get-Acl -LiteralPath $confPath
$proof = [ordered]@{
    result='OK'; at=(Get-Date).ToString('o'); config=$confPath; config_sha256=(Get-FileHash -LiteralPath $confPath -Algorithm SHA256).Hash.ToLower()
    active_config=$activePath; active_config_sha256=(Get-FileHash -LiteralPath $activePath -Algorithm SHA256).Hash.ToLower()
    config_backup=$backupPath; backup_sha256=(Get-FileHash -LiteralPath $backupPath -Algorithm SHA256).Hash.ToLower()
    syntax_check=$syntax.Trim(); syntax_exit=0; reload='native graceful restart event for actual console parent'; reload_at=$reloadAt.ToString('o')
    parent_pid=$apachePid; old_child_pids=$oldChildren; new_child_pids=$newChildren; owner='IMP-PC011\usuario'
    storage_root='C:/ProgramData/ImproovWeb/private/pagamento-fechamento'; flag='OFF'
    private_config_acl=@($fileAcl.Access | ForEach-Object { [ordered]@{ principal=$_.IdentityReference.Value; rights=$_.FileSystemRights.ToString(); type=$_.AccessControlType.ToString(); inherited=$_.IsInherited } })
}
$proof | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $evidencePath -Encoding UTF8
$proof | ConvertTo-Json -Depth 6

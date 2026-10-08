param([Parameter(Mandatory=$true)][ValidateSet(0,1)][int]$Enabled)
$ErrorActionPreference = 'Stop'
$confPath = 'C:/ProgramData/ImproovWeb/private/apache/pagamento-fechamento.conf'
$activePath = 'C:/xampp/apache/conf/httpd.conf'
$expectedActiveHash = 'de20502af80c98e830584eeba869a629c5f4ce292f126790a9f46fc0a8114552'
if ((Get-FileHash -LiteralPath $activePath).Hash.ToLower() -ne $expectedActiveHash) { throw 'Configuracao Apache mudou: parar' }
$original = [IO.File]::ReadAllBytes($confPath)
$text = [Text.Encoding]::UTF8.GetString($original)
$matches = [regex]::Matches($text,'(?m)^\s*SetEnv PAGAMENTO_FECHAMENTO_V2_ENABLED ([01])\s*$')
if ($matches.Count -ne 1) { throw 'Flag ausente ou duplicada' }
$previous = [int]$matches[0].Groups[1].Value
if ($previous -eq $Enabled) { throw 'Flag ja no estado solicitado: nao repetir reload' }
$apachePid = [int](Get-Content 'C:/xampp/apache/logs/httpd.pid')
$parent = Get-CimInstance Win32_Process -Filter "ProcessId=$apachePid"
if ($parent.Name -ne 'httpd.exe' -or $parent.ExecutablePath.ToLower() -ne 'c:\xampp\apache\bin\httpd.exe') { throw 'Instancia Apache inesperada' }
if (@(Get-CimInstance Win32_Service | Where-Object ProcessId -eq $apachePid).Count) { throw 'Apache e servico: parar' }
$owner = Invoke-CimMethod -InputObject $parent -MethodName GetOwner
if ("$($owner.Domain)\$($owner.User)" -ne 'IMP-PC011\usuario') { throw 'Identidade Apache mudou' }
$event = [Threading.EventWaitHandle]::OpenExisting("ap${apachePid}_restart",[Security.AccessControl.EventWaitHandleRights]::Modify)
$oldChildren = @(Get-CimInstance Win32_Process -Filter "Name='httpd.exe'" | Where-Object ParentProcessId -eq $apachePid | ForEach-Object ProcessId)
$stamp = "$(Get-Date -Format yyyyMMdd_HHmmss)_$([guid]::NewGuid().ToString('N').Substring(0,8))"
$backupPath = "C:/ProgramData/ImproovWeb/private/deployment-backups/pagamento_flag_pre_${stamp}.conf"
[IO.File]::WriteAllBytes($backupPath,$original)
$file = [IO.FileInfo]::new($confPath)
$sections = [Security.AccessControl.AccessControlSections]::Access
$acl = $file.GetAccessControl($sections)
$savedAcl = $acl.GetSecurityDescriptorSddlForm($sections)
$sid = [Security.Principal.WindowsIdentity]::GetCurrent().User
if ($sid.Translate([Security.Principal.NTAccount]).Value -ne 'IMP-PC011\usuario') { throw 'Identidade executora inesperada' }
function Write-PrivateFlag([byte[]]$bytes) {
    $mutableAcl = $file.GetAccessControl($sections)
    $mutableAcl.SetAccessRule([Security.AccessControl.FileSystemAccessRule]::new($sid,'Modify','Allow'))
    $file.SetAccessControl($mutableAcl)
    try { [IO.File]::WriteAllBytes($confPath,$bytes) }
    finally {
        $restoreAcl = $file.GetAccessControl($sections)
        $restoreAcl.SetSecurityDescriptorSddlForm($savedAcl,$sections)
        $file.SetAccessControl($restoreAcl)
    }
}
function Check-ApacheSyntax {
    $p = [Diagnostics.Process]::new()
    $p.StartInfo = [Diagnostics.ProcessStartInfo]::new('C:/xampp/apache/bin/httpd.exe','-t -f C:/xampp/apache/conf/httpd.conf')
    $p.StartInfo.UseShellExecute=$false; $p.StartInfo.CreateNoWindow=$true
    $p.StartInfo.RedirectStandardError=$true; $p.StartInfo.RedirectStandardOutput=$true
    [void]$p.Start()
    $output=$p.StandardError.ReadToEnd()+$p.StandardOutput.ReadToEnd()
    $p.WaitForExit(); $code=$p.ExitCode; $p.Dispose()
    if ($code -ne 0 -or $output -notmatch 'Syntax OK') { throw "httpd -t exit ${code}: $output" }
    return $output.Trim()
}
$updated = $text.Replace("SetEnv PAGAMENTO_FECHAMENTO_V2_ENABLED $previous","SetEnv PAGAMENTO_FECHAMENTO_V2_ENABLED $Enabled")
Write-PrivateFlag ([Text.Encoding]::UTF8.GetBytes($updated))
try { $syntax = Check-ApacheSyntax } catch { Write-PrivateFlag $original; $event.Dispose(); throw }
$reloadAt=Get-Date
try { if (-not $event.Set()) { throw 'Sinal de reload falhou' } } finally { $event.Dispose() }
$newChildren=@()
for ($attempt=0; $attempt -lt 30; $attempt++) {
    Start-Sleep -Milliseconds 500
    $newChildren=@(Get-CimInstance Win32_Process -Filter "Name='httpd.exe'" | Where-Object { $_.ParentProcessId -eq $apachePid -and $_.ProcessId -notin $oldChildren } | ForEach-Object ProcessId)
    if ($newChildren.Count) { break }
}
if (-not $newChildren.Count) { throw 'Reload sem novo child verificado: estado deve ser auditado' }
if ($file.GetAccessControl($sections).GetSecurityDescriptorSddlForm($sections) -ne $savedAcl) { throw 'ACL nao restaurada' }
$proof=[ordered]@{ result='OK'; at=(Get-Date).ToString('o'); flag_before=$previous; flag_after=$Enabled; syntax_exit=0; syntax=$syntax; reload='native graceful restart event'; reload_at=$reloadAt.ToString('o'); parent_pid=$apachePid; old_child_pids=$oldChildren; new_child_pids=$newChildren; config_sha256=(Get-FileHash $confPath).Hash.ToLower(); active_config_sha256=(Get-FileHash $activePath).Hash.ToLower(); acl_preserved=$true; private_backup=$backupPath }
$proof | ConvertTo-Json -Depth 6 | Set-Content "C:/xampp/htdocs/ImproovWeb/docs/evidence/pagamento-canary-flag-${Enabled}-${stamp}.json" -Encoding UTF8
$proof | ConvertTo-Json -Depth 6

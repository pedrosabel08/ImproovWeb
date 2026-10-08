$ErrorActionPreference = 'Stop'
$base = 'C:\ProgramData\ImproovWeb\private'
$account = [Security.Principal.NTAccount]::new('IMP-PC011\usuario')
$sid = $account.Translate([Security.Principal.SecurityIdentifier])
if ([Security.Principal.WindowsIdentity]::GetCurrent().User.Value -ne $sid.Value) { throw 'Executar como IMP-PC011\usuario' }
foreach ($p in @('C:\ProgramData','C:\ProgramData\ImproovWeb',$base)) {
    if (Test-Path -LiteralPath $p) {
        if ((Get-Item -Force -LiteralPath $p).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Reparse point detectado' }
    }
}
if ((Test-Path -LiteralPath $base) -and @(Get-ChildItem -LiteralPath $base -Force).Count -gt 0) { throw 'Base existente não vazia: auditar antes de reprovisionar' }
New-Item -ItemType Directory -Path $base -Force | Out-Null
function Protect-Directory([string]$path, [string]$rights, [bool]$inheritUser) {
    $dir = [IO.DirectoryInfo]::new($path)
    $acl = $dir.GetAccessControl([Security.AccessControl.AccessControlSections]::Access)
    $acl.SetAccessRuleProtection($true,$false)
    foreach ($rule in @($acl.Access)) { $acl.RemoveAccessRuleSpecific($rule) }
    $flags = [Security.AccessControl.InheritanceFlags]'ContainerInherit, ObjectInherit'
    foreach ($principal in @('S-1-5-18','S-1-5-32-544')) {
        $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.SecurityIdentifier]::new($principal),'FullControl',$flags,'None','Allow'))
    }
    $userFlags = [Security.AccessControl.InheritanceFlags]::None
    if ($inheritUser) { $userFlags = $flags }
    $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($sid,$rights,$userFlags,'None','Allow'))
    $dir.SetAccessControl($acl)
}
Protect-Directory $base 'Modify' $false
foreach ($child in @('pagamento-fechamento','deployment-backups','apache')) {
    $p = Join-Path $base $child
    New-Item -ItemType Directory -Path $p | Out-Null
    $rights = 'Modify'
    if ($child -eq 'apache') { $rights = 'ReadAndExecute' }
    Protect-Directory $p $rights $true
}
foreach ($child in @('staging','definitivo','locks')) {
    New-Item -ItemType Directory -Path (Join-Path "$base\pagamento-fechamento" $child) | Out-Null
}
Protect-Directory $base 'ReadAndExecute' $false
Get-ChildItem -LiteralPath $base -Directory -Recurse | ForEach-Object {
    if ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Reparse point detectado' }
    [pscustomobject]@{Path=$_.FullName;Protected=(Get-Acl -LiteralPath $_.FullName).AreAccessRulesProtected;ACL=(Get-Acl -LiteralPath $_.FullName).AccessToString}
} | ConvertTo-Json -Depth 4

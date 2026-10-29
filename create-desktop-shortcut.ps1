$WshShell = New-Object -ComObject WScript.Shell
$DesktopPath = [System.Environment]::GetFolderPath('Desktop')
$ShortcutPath = Join-Path $DesktopPath "Hospital Management System.lnk"
$TargetDir = "C:\xampp\hospital-management-system-main"
$ExePath = Join-Path $TargetDir "Hospital-Management-System.exe"

$Shortcut = $WshShell.CreateShortcut($ShortcutPath)
$Shortcut.TargetPath = $ExePath
$Shortcut.WorkingDirectory = $TargetDir
$Shortcut.Description = "Hospital Management System (HMS) Desktop Application"
$Shortcut.Save()

Write-Host "Desktop shortcut updated successfully: $ShortcutPath -> $ExePath"

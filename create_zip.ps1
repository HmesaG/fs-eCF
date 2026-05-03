$pluginName = "eCF_GMV"
$baseDir = "e:\Empresas\GMV\Proyectos Antigravity\FacturaScripts\facturascripts"
$sourceDir = Join-Path $baseDir "Plugins\$pluginName"
$zipFile = Join-Path $baseDir "$pluginName.zip"

if (Test-Path $zipFile) { Remove-Item $zipFile }

Write-Host "Creating ZIP: $zipFile"

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($zipFile, [System.IO.Compression.ZipArchiveMode]::Create)

# 1. Crear la entrada del directorio raíz (importante para algunos validadores)
[System.IO.Compression.ZipFileExtensions]::CreateEntry($zip, "$pluginName/")

# 2. Agregar todos los archivos con rutas usando forward slash (/)
Get-ChildItem -Path $sourceDir -Recurse | Where-Object { !$_.PSIsContainer } | ForEach-Object {
    $relativePath = $_.FullName.Substring($sourceDir.Length + 1).Replace("\", "/")
    $archivePath = "$pluginName/$relativePath"
    Write-Host "Adding: $archivePath"
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $archivePath, [System.IO.Compression.CompressionLevel]::Optimal)
}

$zip.Dispose()
Write-Host "ZIP created successfully with forward slashes and root directory entry."

$pluginName = "eCF_GMV"
$baseDir    = "e:\Empresas\GMV\Proyectos Antigravity\FacturaScripts\facturascripts"
$sourceDir  = Join-Path $baseDir "Plugins\$pluginName"
$zipFile    = Join-Path $baseDir "$pluginName.zip"

# Carpetas y archivos a EXCLUIR del ZIP
$excludeDirs  = @('Certificados', 'XSD', 'Test', 'Cron')
$excludeFiles = @('PLAN_DE_TRABAJO.md', 'ecf-gmv-funciones.html', 'image.png', 'openssl.cnf')

if (Test-Path $zipFile) { Remove-Item $zipFile }

Write-Host "Creando ZIP: $zipFile" -ForegroundColor Cyan

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($zipFile, [System.IO.Compression.ZipArchiveMode]::Create)

Get-ChildItem -Path $sourceDir -Recurse | Where-Object { !$_.PSIsContainer } | ForEach-Object {
    $file = $_

    # Verificar si el archivo está en una carpeta excluida
    $relativePath = $file.FullName.Substring($sourceDir.Length + 1)
    $parts        = $relativePath -split '\\'
    $inExcludedDir = $excludeDirs | Where-Object { $parts -contains $_ }

    # Verificar si el nombre del archivo está excluido
    $isExcludedFile = $excludeFiles -contains $file.Name

    if ($inExcludedDir -or $isExcludedFile) {
        Write-Host "  SKIPPING: $relativePath" -ForegroundColor Yellow
        return
    }

    $archivePath = "$pluginName/" + $relativePath.Replace("\", "/")
    Write-Host "  Adding: $archivePath" -ForegroundColor Gray
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $archivePath, [System.IO.Compression.CompressionLevel]::Optimal)
}

$zip.Dispose()
Write-Host ""
Write-Host "ZIP creado exitosamente: $zipFile" -ForegroundColor Green

# Verificación de estructura
$zipVerify = [System.IO.Compression.ZipFile]::OpenRead($zipFile)
$iniEntry  = $zipVerify.Entries | Where-Object { $_.FullName -match 'facturascripts\.ini' }
$zipVerify.Dispose()

if ($iniEntry) {
    $parts = $iniEntry.FullName -split '/'
    if ($parts.Count -eq 2) {
        Write-Host "Verificacion OK: Estructura correcta ($($iniEntry.FullName))" -ForegroundColor Green
    } else {
        Write-Host "ERROR: Separadores incorrectos en el ZIP!" -ForegroundColor Red
    }
} else {
    Write-Host "ERROR: No se encontro facturascripts.ini en el ZIP!" -ForegroundColor Red
}


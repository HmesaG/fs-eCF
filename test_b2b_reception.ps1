$url = "http://fs-8fink9-9e0299-31-97-100-82.traefik.me/index.php?page=ApiRecepcion"
$xmlFilePath = ".\sample_b2b.xml"

# Si la ruta amigable no funciona, descomenta la siguiente línea y prueba con esta:
# $url = "http://fs-8fink9-9e0299-31-97-100-82.traefik.me/ApiRecepcion"

Write-Host "Enviando XML de prueba B2B a $url ..."

try {
    $xmlContent = Get-Content -Path $xmlFilePath -Raw
    $response = Invoke-RestMethod -Uri $url -Method Post -Body $xmlContent -ContentType "application/xml"
    
    Write-Host "Respuesta del servidor:" -ForegroundColor Green
    # La respuesta debería ser el XML del ARECF
    if ($response.InnerXml) {
        Write-Host $response.InnerXml
    } else {
        Write-Host $response
    }
} catch {
    Write-Host "Error en la solicitud:" -ForegroundColor Red
    Write-Host $_.Exception.Message
    if ($_.ErrorDetails) {
        Write-Host $_.ErrorDetails.Message
    }
}

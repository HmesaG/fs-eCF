<?php
/**
 * Plugin eCF-GMV — Generador de PDF para facturas electrónicas (e-CF)
 * Genera el comprobante impreso con QR de verificación DGII.
 *
 * Usa las librerías del Core de FacturaScripts:
 *   - rospdf/pdf-php  (Cpdf / Cezpdf)
 *   - chillerlan/php-qrcode
 *
 * NO requiere PlantillasPDF.
 */

namespace FacturaScripts\Plugins\eCF_GMV\Lib\PDF;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Empresa;
use Cezpdf;

/**
 * Genera el PDF del comprobante e-CF con código QR de la DGII.
 *
 * @author eCF-GMV Plugin
 */
class ECFPdfGenerator
{
    // Márgenes y medidas en puntos (1mm ≈ 2.835pt)
    const PAGE_W   = 595;  // A4 ancho
    const PAGE_H   = 842;  // A4 alto
    const MARGIN_L = 40;
    const MARGIN_R = 40;
    const MARGIN_T = 40;
    const MARGIN_B = 40;

    // Colores corporativos (RGB 0–255)
    const COLOR_PRIMARY   = [0,   83,  156];   // azul DGII
    const COLOR_SECONDARY = [50,  50,   50];   // gris oscuro
    const COLOR_LIGHT     = [240, 240, 240];   // gris claro
    const COLOR_WHITE     = [255, 255, 255];

    /** @var Cezpdf */
    private $pdf;

    /** @var FacturaCliente */
    private $factura;

    /** @var Empresa */
    private $empresa;

    /** @var float Posición Y actual */
    private $y;

    /** @var string Ruta temporal del PNG del QR */
    private $qrPath = '';

    public function __construct(FacturaCliente $factura)
    {
        $this->factura = $factura;

        $this->empresa = new Empresa();
        $this->empresa->loadFromCode($factura->idempresa);

        // Inicializar Cezpdf (librería del Core)
        $this->pdf = new Cezpdf('a4', 'portrait');
        $this->pdf->addInfo('Title',   'e-CF ' . $factura->codigo);
        $this->pdf->addInfo('Author',  $this->empresa->nombre);
        $this->pdf->addInfo('Subject', 'Comprobante Fiscal Electrónico');

        $this->y = self::PAGE_H - self::MARGIN_T;
    }

    /**
     * Genera el PDF y devuelve el contenido binario.
     */
    public function generate(): string
    {
        $this->drawHeader();
        $this->drawECFInfo();
        $this->drawClientInfo();
        $this->drawLines();
        $this->drawTotals();
        $this->drawQR();
        $this->drawFooter();

        $output = $this->pdf->ezOutput();

        // Limpiar QR temporal
        if ($this->qrPath && file_exists($this->qrPath)) {
            @unlink($this->qrPath);
        }

        return $output;
    }

    // -------------------------------------------------------------------------
    // SECCIÓN 1: ENCABEZADO EMPRESA
    // -------------------------------------------------------------------------
    private function drawHeader(): void
    {
        $x = self::MARGIN_L;
        $w = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;

        // Barra azul superior
        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_PRIMARY));
        $this->pdf->filledRectangle($x, $this->y - 50, $w, 50);

        // Nombre empresa en blanco
        $this->pdf->setColor(1, 1, 1);
        $this->pdf->setFontSize(16);
        $this->pdf->addText($x + 10, $this->y - 30, 16,
            Tools::fixHtml($this->empresa->nombre), $w - 20, 'left');

        // RNC empresa
        $this->pdf->setFontSize(9);
        $this->pdf->addText($x + 10, $this->y - 44, 9,
            'RNC: ' . ($this->empresa->cifnif ?? ''), $w - 20, 'left');

        $this->y -= 60;

        // Dirección y teléfono empresa
        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_SECONDARY));
        $this->pdf->setFontSize(8);
        $dir  = Tools::fixHtml($this->empresa->direccion ?? '');
        $tel  = $this->empresa->telefono1 ?? '';
        $mail = $this->empresa->email ?? '';

        $this->pdf->addText($x, $this->y, 8, $dir . '   Tel: ' . $tel . '   ' . $mail, $w, 'left');
        $this->y -= 12;

        // Línea separadora
        $this->drawHRule();
    }

    // -------------------------------------------------------------------------
    // SECCIÓN 2: DATOS DEL e-CF
    // -------------------------------------------------------------------------
    private function drawECFInfo(): void
    {
        $x = self::MARGIN_L;
        $w = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;

        // Recuadro gris con título del comprobante
        $tipoLabel = $this->getTipoLabel();
        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_LIGHT));
        $this->pdf->filledRectangle($x, $this->y - 28, $w, 28);

        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_PRIMARY));
        $this->pdf->setFontSize(12);
        $this->pdf->addText($x + 8, $this->y - 18, 12, $tipoLabel, $w / 2 - 10, 'left');

        // NCF / número e-CF
        $ncf = $this->factura->codigo ?? '';
        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_SECONDARY));
        $this->pdf->setFontSize(11);
        $this->pdf->addText($x + $w / 2, $this->y - 18, 11, 'No. ' . $ncf, $w / 2, 'right');

        $this->y -= 36;

        // Fecha y condiciones — dos columnas
        $this->pdf->setFontSize(8);
        $fecha = date('d/m/Y', strtotime($this->factura->fecha ?? 'now'));

        $col1 = [
            ['Fecha de emisión:', $fecha],
            ['Fecha de vencimiento:', date('d/m/Y', strtotime($this->factura->fechavencimiento ?? 'now'))],
        ];
        $col2 = [
            ['Forma de pago:', Tools::fixHtml($this->factura->formapago ?? '')],
            ['Moneda:', $this->factura->coddivisa ?? 'DOP'],
        ];

        foreach ($col1 as $i => [$label, $valor]) {
            $yRow = $this->y - ($i * 12);
            $this->pdf->setColor(0.3, 0.3, 0.3);
            $this->pdf->addText($x, $yRow, 8, $label, $w / 2 - 10, 'left');
            $this->pdf->setColor(0, 0, 0);
            $this->pdf->addText($x + 110, $yRow, 8, $valor, $w / 2 - 110, 'left');
        }
        foreach ($col2 as $i => [$label, $valor]) {
            $yRow = $this->y - ($i * 12);
            $this->pdf->setColor(0.3, 0.3, 0.3);
            $this->pdf->addText($x + $w / 2, $yRow, 8, $label, $w / 4, 'left');
            $this->pdf->setColor(0, 0, 0);
            $this->pdf->addText($x + $w / 2 + 100, $yRow, 8, $valor, $w / 4, 'left');
        }

        $this->y -= (count($col1) * 12) + 8;
        $this->drawHRule();
    }

    // -------------------------------------------------------------------------
    // SECCIÓN 3: DATOS DEL CLIENTE
    // -------------------------------------------------------------------------
    private function drawClientInfo(): void
    {
        $x = self::MARGIN_L;
        $w = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;

        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_PRIMARY));
        $this->pdf->setFontSize(9);
        $this->pdf->addText($x, $this->y, 9, 'COMPRADOR / CLIENTE', $w, 'left');
        $this->y -= 14;

        $this->pdf->setColor(0, 0, 0);
        $this->pdf->setFontSize(8);

        $rows = [
            ['Nombre/Razón Social:', Tools::fixHtml($this->factura->nombrecliente ?? '')],
            ['RNC/Cédula:', $this->factura->cifnif ?? 'Consumidor Final'],
            ['Dirección:', Tools::fixHtml($this->factura->direccion ?? '')],
            ['Teléfono:', $this->factura->telefono1 ?? ''],
            ['Email:', $this->factura->email ?? ''],
        ];

        foreach ($rows as [$label, $valor]) {
            if (empty(trim($valor))) {
                continue;
            }
            $this->pdf->setColor(0.4, 0.4, 0.4);
            $this->pdf->addText($x, $this->y, 8, $label, 100, 'left');
            $this->pdf->setColor(0, 0, 0);
            $this->pdf->addText($x + 105, $this->y, 8, $valor, $w - 105, 'left');
            $this->y -= 11;
        }

        $this->y -= 4;
        $this->drawHRule();
    }

    // -------------------------------------------------------------------------
    // SECCIÓN 4: LÍNEAS DE LA FACTURA
    // -------------------------------------------------------------------------
    private function drawLines(): void
    {
        $x = self::MARGIN_L;
        $w = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;

        // Encabezado de tabla
        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_PRIMARY));
        $this->pdf->filledRectangle($x, $this->y - 16, $w, 16);

        $this->pdf->setColor(1, 1, 1);
        $this->pdf->setFontSize(8);

        $cols = [
            ['#',          $x,              20,  'center'],
            ['Descripción',$x + 22,        230,  'left'],
            ['Cant.',      $x + 254,        35,  'right'],
            ['Precio',     $x + 291,        65,  'right'],
            ['Dto%',       $x + 358,        35,  'right'],
            ['ITBIS',      $x + 395,        35,  'right'],
            ['Total',      $x + 432,        $w - 432, 'right'],
        ];

        foreach ($cols as [$titulo, $cx, $cw, $align]) {
            $this->pdf->addText($cx + 2, $this->y - 11, 8, $titulo, $cw, $align);
        }
        $this->y -= 20;

        // Filas
        $lineas = $this->factura->getLines();
        $shade  = false;

        foreach ($lineas as $linea) {
            if ($this->y < self::MARGIN_B + 80) {
                $this->pdf->ezNewPage();
                $this->y = self::PAGE_H - self::MARGIN_T;
            }

            if ($shade) {
                $this->pdf->setColor(0.96, 0.96, 0.97);
                $this->pdf->filledRectangle($x, $this->y - 14, $w, 14);
            }
            $shade = !$shade;

            $this->pdf->setColor(0, 0, 0);
            $this->pdf->setFontSize(8);

            $desc   = Tools::fixHtml($linea->descripcion ?? '');
            $cant   = Tools::number($linea->cantidad);
            $precio = Tools::money($linea->pvpunitario, $this->factura->coddivisa);
            $dto    = $linea->dtopor > 0 ? Tools::number($linea->dtopor) . '%' : '-';
            $itbis  = $linea->iva > 0 ? Tools::number($linea->iva) . '%' : '0%';
            $total  = Tools::money($linea->pvptotal, $this->factura->coddivisa);

            $rowData = [
                ['-',    $x,     20,  'center'],
                [$desc,  $x+22,  228, 'left'],
                [$cant,  $x+252,  37, 'right'],
                [$precio,$x+291,  65, 'right'],
                [$dto,   $x+358,  35, 'right'],
                [$itbis, $x+395,  35, 'right'],
                [$total, $x+432,  $w-432, 'right'],
            ];
            foreach ($rowData as [$val, $cx, $cw, $align]) {
                $this->pdf->addText($cx + 2, $this->y - 10, 8, $val, $cw, $align);
            }
            $this->y -= 15;
        }

        $this->y -= 4;
        $this->drawHRule();
    }

    // -------------------------------------------------------------------------
    // SECCIÓN 5: TOTALES
    // -------------------------------------------------------------------------
    private function drawTotals(): void
    {
        $x    = self::MARGIN_L;
        $w    = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
        $xVal = $x + $w - 130;
        $wVal = 125;

        $fac = $this->factura;

        $totals = [
            ['Subtotal (sin ITBIS):', Tools::money($fac->neto,         $fac->coddivisa)],
            ['ITBIS (18%):',          Tools::money($fac->totaliva,     $fac->coddivisa)],
        ];
        if ($fac->totalirpf ?? 0) {
            $totals[] = ['Retención ISR:', '-' . Tools::money($fac->totalirpf, $fac->coddivisa)];
        }

        foreach ($totals as [$label, $valor]) {
            $this->pdf->setColor(0.3, 0.3, 0.3);
            $this->pdf->setFontSize(9);
            $this->pdf->addText($xVal - 130, $this->y, 9, $label, 125, 'right');
            $this->pdf->setColor(0, 0, 0);
            $this->pdf->addText($xVal, $this->y, 9, $valor, $wVal, 'right');
            $this->y -= 13;
        }

        // Línea total
        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_PRIMARY));
        $this->pdf->filledRectangle($xVal - 135, $this->y - 5, 265, 18);

        $this->pdf->setColor(1, 1, 1);
        $this->pdf->setFontSize(11);
        $this->pdf->addText($xVal - 130, $this->y, 11, 'TOTAL A PAGAR:', 125, 'right');
        $this->pdf->addText($xVal, $this->y, 11,
            Tools::money($fac->total, $fac->coddivisa), $wVal, 'right');

        $this->y -= 28;
    }

    // -------------------------------------------------------------------------
    // SECCIÓN 6: CÓDIGO QR DGII
    // -------------------------------------------------------------------------
    private function drawQR(): void
    {
        $x = self::MARGIN_L;
        $w = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;

        // Contenido del QR: URL de verificación DGII
        $qrContent = $this->buildQRContent();
        if (empty($qrContent)) {
            return;
        }

        // Generar PNG del QR
        $qrFile = FS_FOLDER . '/MyFiles/Cache/ecf_qr_' . $this->factura->idfactura . '.png';

        try {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'imageBase64'=> false,
                'scale'      => 5,
                'margin'     => 1,
            ]);
            $qr = new QRCode($options);
            file_put_contents($qrFile, $qr->render($qrContent));
            $this->qrPath = $qrFile;
        } catch (\Exception $e) {
            Tools::log('eCF-GMV')->warning('Error generando QR: ' . $e->getMessage());
            return;
        }

        // Insertar QR en PDF (60x60 pt ≈ 21mm)
        $qrSize = 90;
        $this->y -= 8;

        // Recuadro informativo eCF a la izquierda del QR
        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_LIGHT));
        $this->pdf->filledRectangle($x, $this->y - $qrSize - 10, $w - $qrSize - 20, $qrSize + 10);

        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_PRIMARY));
        $this->pdf->setFontSize(8);
        $this->pdf->addText($x + 5, $this->y - 10, 8, 'INFORMACIÓN DEL COMPROBANTE ELECTRÓNICO', $w - $qrSize - 30, 'left');

        $this->pdf->setColor(0, 0, 0);
        $this->pdf->setFontSize(7);

        $ecfInfo = $this->getECFInfo();
        $lineY = $this->y - 22;
        foreach ($ecfInfo as [$label, $valor]) {
            $this->pdf->setColor(0.4, 0.4, 0.4);
            $this->pdf->addText($x + 5, $lineY, 7, $label, 90, 'left');
            $this->pdf->setColor(0, 0, 0);
            $this->pdf->addText($x + 98, $lineY, 7, $valor, $w - $qrSize - 120, 'left');
            $lineY -= 10;
        }

        // Insertar imagen QR (esquina inferior derecha de la sección)
        $qrX = self::PAGE_W - self::MARGIN_R - $qrSize - 5;
        $qrY = $this->y - $qrSize - 5;

        if (file_exists($qrFile)) {
            $this->pdf->addPngFromFile($qrFile, $qrX, $qrY, $qrSize, $qrSize);
        }

        // Leyenda bajo el QR
        $this->pdf->setColor(0.4, 0.4, 0.4);
        $this->pdf->setFontSize(6);
        $this->pdf->addText($qrX, $qrY - 8, 6, 'Verificar en DGII', $qrSize, 'center');

        $this->y -= ($qrSize + 20);
    }

    // -------------------------------------------------------------------------
    // SECCIÓN 7: PIE DE PÁGINA
    // -------------------------------------------------------------------------
    private function drawFooter(): void
    {
        $x = self::MARGIN_L;
        $w = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;

        $this->drawHRule();

        $this->pdf->setColor(0.5, 0.5, 0.5);
        $this->pdf->setFontSize(7);

        $leyenda = 'Este documento es una representación impresa de un Comprobante Fiscal Electrónico (e-CF). '
            . 'Válido conforme al Art. 44 del Decreto 254-06 de la República Dominicana. '
            . 'Generado por eCF-GMV · ' . date('d/m/Y H:i');

        $this->pdf->addText($x, $this->y, 7, $leyenda, $w, 'center');
        $this->y -= 10;

        // Texto legal opcional de observaciones
        if (!empty($this->factura->observaciones)) {
            $this->pdf->setFontSize(7);
            $this->pdf->addText($x, $this->y, 7,
                'Obs: ' . Tools::fixHtml($this->factura->observaciones), $w, 'left');
        }
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------
    private function drawHRule(): void
    {
        $this->pdf->setColor(...array_map(fn($c) => $c / 255, self::COLOR_PRIMARY));
        $this->pdf->line(self::MARGIN_L, $this->y, self::PAGE_W - self::MARGIN_R, $this->y);
        $this->y -= 8;
        $this->pdf->setColor(0, 0, 0);
    }

    private function getTipoLabel(): string
    {
        // Tipos DGII: 31=Fiscal, 32=Consumidor Final, 33=ND, 34=NC, 41=Gastos Menor
        $tipo = substr($this->factura->codigo ?? '', 1, 2);
        $tipos = [
            '31' => 'FACTURA DE CRÉDITO FISCAL ELECTRÓNICA',
            '32' => 'FACTURA DE CONSUMO ELECTRÓNICA',
            '33' => 'NOTA DE DÉBITO ELECTRÓNICA',
            '34' => 'NOTA DE CRÉDITO ELECTRÓNICA',
            '41' => 'COMPRAS ELECTRÓNICO',
            '43' => 'GASTOS MENORES ELECTRÓNICO',
            '44' => 'REGÍMENES ESPECIALES ELECTRÓNICO',
            '45' => 'GUBERNAMENTAL ELECTRÓNICO',
            '46' => 'EXPORTACIONES ELECTRÓNICO',
            '47' => 'PARA USO GUBERNAMENTAL ELECTRÓNICO',
        ];
        return $tipos[$tipo] ?? 'COMPROBANTE FISCAL ELECTRÓNICO';
    }

    private function buildQRContent(): string
    {
        // Contenido estándar QR DGII: RNC emisor + NCF + total + fecha
        $rnc     = $this->empresa->cifnif ?? '';
        $ncf     = $this->factura->codigo ?? '';
        $total   = number_format((float)($this->factura->total ?? 0), 2, '.', '');
        $fecha   = date('Ymd', strtotime($this->factura->fecha ?? 'now'));
        $rncComp = $this->factura->cifnif ?? '00000000000';

        if (empty($rnc) || empty($ncf)) {
            return '';
        }

        // Obtener el Código de Seguridad (primeros 6 caracteres del SignatureValue del XML firmado)
        $codigoSeguridad = '';
        $xmlFirmado = $this->factura->ecf_xml_firmado ?? '';
        if (!empty($xmlFirmado)) {
            if (preg_match('/<ds:SignatureValue[^>]*>(.*?)<\/ds:SignatureValue>/is', $xmlFirmado, $matches) || preg_match('/<SignatureValue[^>]*>(.*?)<\/SignatureValue>/is', $xmlFirmado, $matches)) {
                $codigoSeguridad = substr(trim($matches[1]), 0, 6);
            }
        }

        // URL de consulta DGII (formato oficial)
        $url = "https://ecf.dgii.gov.do/consulta?RNCEmisor={$rnc}&NCF={$ncf}&RNCComprador={$rncComp}&FechaEmision={$fecha}&MontoTotal={$total}";
        if (!empty($codigoSeguridad)) {
            $url .= "&CodigoSeguridad={$codigoSeguridad}";
        }

        return $url;
    }

    private function getECFInfo(): array
    {
        $codigoSeguridad = '';
        $xmlFirmado = $this->factura->ecf_xml_firmado ?? '';
        if (!empty($xmlFirmado)) {
            if (preg_match('/<ds:SignatureValue[^>]*>(.*?)<\/ds:SignatureValue>/is', $xmlFirmado, $matches) || preg_match('/<SignatureValue[^>]*>(.*?)<\/SignatureValue>/is', $xmlFirmado, $matches)) {
                $codigoSeguridad = substr(trim($matches[1]), 0, 6);
            }
        }

        return [
            ['NCF:',              $this->factura->codigo ?? ''],
            ['Tipo eCF:',         substr($this->factura->codigo ?? '', 1, 2)],
            ['RNC Emisor:',       $this->empresa->cifnif ?? ''],
            ['RNC Comprador:',    $this->factura->cifnif ?? 'Consumidor Final'],
            ['Cód. Seguridad:',   $codigoSeguridad],
            ['Fecha Firma:',      date('d/m/Y H:i')],
            ['Ambiente:',         defined('ECF_AMBIENTE') ? ECF_AMBIENTE : 'Producción'],
        ];
    }
}

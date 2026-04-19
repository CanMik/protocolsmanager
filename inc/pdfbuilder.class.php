<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

// ── TCPDF is bundled in GLPI's Composer vendor tree (tecnickcom/tcpdf ^6.10). ──
// The GLPI autoloader registers \TCPDF automatically when running inside GLPI.
// The guard below only fires in edge-cases where the autoloader has not yet run.
if (!class_exists('TCPDF', false)) {
    $tcpdf_path = GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once $tcpdf_path;
    }
}

/**
 * PDF generator for Protocols Manager — powered by GLPI's bundled TCPDF.
 *
 * GLPI ships tecnickcom/tcpdf ^6.10 as a first-party Composer dependency.
 * This class extends TCPDF directly, so the plugin carries no external PDF
 * vendor bundle.  The lib/fpdf/ directory used in earlier versions has been
 * removed entirely.
 *
 * Key improvements over the previous FPDF implementation:
 *  • Native UTF-8         — iconv() conversion completely removed.
 *  • Richer font stack    — any TTF/OTF font supported by TCPDF.
 *  • DejaVu Sans support  — covers accented/non-Latin characters beyond ISO-8859-1.
 *  • Smaller plugin size  — no bundled font files (TCPDF fonts live in GLPI vendor).
 *  • State column         — equipment state now shown and passed to PDF.
 *
 * Document layout (top → bottom, every page):
 *   [Logo — centred, when configured]
 *   [Document number — left]   [City + date — right]
 *   [Title — centred, bold]
 *   [Upper content]
 *   [Equipment table with State column]
 *   [Main content]
 *   [Signature block: Administrator | User]
 *   [Footer — auto-printed via TCPDF::Footer()]
 *
 * @package glpi\protocolsmanager
 */
class PluginProtocolsmanagerPdfBuilder extends TCPDF
{
    // -----------------------------------------------------------------------
    // Constants
    // -----------------------------------------------------------------------

    /** CSS-pixel (96 dpi) to millimetre conversion. */
    private const PX_TO_MM = 0.2646;

    /**
     * Plugin font name → TCPDF font family (lower-case).
     * TCPDF built-in fonts: helvetica, courier, times, dejavusans.
     * "dejavusans" ships in GLPI's vendor TCPDF fonts directory and
     * supports the full Unicode BMP — ideal for multilingual documents.
     */
    private const FONT_MAP = [
        'Courier'         => 'courier',
        'Helvetica'       => 'helvetica',
        'Times'           => 'times',
        'Roboto'          => 'dejavusans',
        'Liberation-Sans' => 'helvetica',
        'Istok'           => 'helvetica',
        'UbuntuMono'      => 'courier',
        'DroidSerif'      => 'times',
        'DejaVu Sans'     => 'dejavusans',
    ];

    // -----------------------------------------------------------------------
    // Internal state
    // -----------------------------------------------------------------------

    private string $footerContent = '';
    private string $fontFamily    = 'helvetica';
    private int    $baseFontSize  = 9;

    // -----------------------------------------------------------------------
    // Constructor
    // -----------------------------------------------------------------------

    public function __construct()
    {
        // 6th arg: disk_cache (false = keep in memory).
        // 7th arg: pdfa (false = standard PDF, not PDF/A).
        // Compression is enabled separately via setCompression(true).
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->setCompression(true); // Enable zlib compression — reduces file size ~40-60%.
        // Disable built-in header. Keep footer ENABLED so TCPDF calls
        // OUR Footer() override on every page — we control its output entirely.
        $this->setPrintHeader(false);
        $this->setPrintFooter(true);
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    public function setFooterContent(string $text): void
    {
        $this->footerContent = $text;
    }

    public function setBaseFont(string $pluginFont, int $size): void
    {
        $this->fontFamily   = self::FONT_MAP[$pluginFont] ?? 'helvetica';
        $this->baseFontSize = max(7, min(14, $size));
    }

    /**
     * Generate the PDF and return its raw binary content.
     *
     * Expected keys in $data
     * ──────────────────────
     * string  orientation    'Portrait' | 'Landscape'
     * string  logo           Absolute path to logo file (may be empty)
     * int     logo_width     Logo width in px (nullable)
     * int     logo_height    Logo height in px (nullable)
     * string  prot_num       Correlative document number
     * string  city           City for the header
     * string  title          Document title (substitutions already applied)
     * string  upper_content  Upper text block (substitutions applied)
     * string  content        Main text block (substitutions applied)
     * int     serial_mode    1 = separate serial + inventory / 2 = merged
     * int     author_state   1 = generator login name / 2 = fixed name
     * string  author_name    Fixed author name (when author_state == 2)
     * string  author         Name of the user generating the document
     * string  owner          Name of the equipment owner
     * array   number         Selected row indices
     * array   type_name[]    Item type, indexed by number[]
     * array   man_name[]     Manufacturer, indexed by number[]
     * array   mod_name[]     Model, indexed by number[]
     * array   serial[]       Serial number, indexed by number[]
     * array   otherserial[]  Inventory number, indexed by number[]
     * array   item_name[]    Item name, indexed by number[]
     * array   comments[]     Comment text, indexed by number[]
     * array   state_name[]   State label resolved from states_id, indexed by number[]
     *
     * @return string Raw PDF binary
     */
    public function generate(array $data): string
    {
        $orientation = (strtolower($data['orientation'] ?? 'portrait') === 'landscape') ? 'L' : 'P';

        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 22);
        $this->setCellPadding(1);

        // PDF document metadata.
        $docTitle  = $data['title']  ?? '';
        $docAuthor = ((int)($data['author_state'] ?? 1) === 2)
            ? ($data['author_name'] ?? '')
            : ($data['author']      ?? '');
        $this->SetTitle($docTitle);
        $this->SetAuthor($docAuthor);
        $this->SetSubject($docTitle);
        // PLUGIN_PROTOCOLSMANAGER_VERSION is defined in setup.php (loaded by GLPI before any plugin class).
        $pluginVersion = defined('PLUGIN_PROTOCOLSMANAGER_VERSION') ? PLUGIN_PROTOCOLSMANAGER_VERSION : '?';
        $this->SetCreator('protocolsmanager v' . $pluginVersion);
        $this->SetKeywords('GLPI protocolsmanager');

        $this->AddPage($orientation);

        // Usable width = page width − left margin − right margin.
        $pageW = $this->getPageWidth() - 30;

        $this->renderLogo($data, $pageW);
        $this->renderHeader($data, $pageW);
        $this->renderUpperContent($data, $pageW);
        $this->renderItemsTable($data, $pageW);
        $this->renderContent($data, $pageW);
        $this->renderSignature($data, $pageW);

        return $this->Output('', 'S');
    }

    // -----------------------------------------------------------------------
    // TCPDF footer hook — called automatically at every page break
    // -----------------------------------------------------------------------

    public function Footer(): void
    {
        // This override is always called instead of the parent TCPDF footer,
        // which would print 'Powered by TCPDF (www.tcpdf.org)' and page numbers.
        // When footerContent is empty we render nothing — blank footer area.
        if (empty($this->footerContent)) {
            return; // intentionally blank — no parent::Footer() call
        }

        $this->SetY(-18);
        $this->setFont($this->fontFamily, 'I', 8);
        $this->setTextColor(100, 100, 100);

        // Literal '\n' sequences stored in DB → pipe-separated on one line.
        $text = str_replace(['\\n', "\n"], ' | ', $this->footerContent);
        $this->Cell(0, 5, $text, 0, 0, 'C');

        $this->setTextColor(0, 0, 0);
        // Note: deliberately no parent::Footer() call.
    }

    // -----------------------------------------------------------------------
    // Rendering blocks
    // -----------------------------------------------------------------------

    private function renderLogo(array $data, float $pageW): void
    {
        $logo = $data['logo'] ?? '';
        if (empty($logo) || !file_exists($logo)) {
            return;
        }

        $ext  = strtolower(pathinfo($logo, PATHINFO_EXTENSION));
        $type = ($ext === 'png') ? 'PNG' : 'JPEG';

        $wMm = !empty($data['logo_width'])
            ? (int)$data['logo_width']  * self::PX_TO_MM
            : 50.0;
        $hMm = !empty($data['logo_height'])
            ? (int)$data['logo_height'] * self::PX_TO_MM
            : 15.0;

        $imgX = 15 + ($pageW - $wMm) / 2;

        try {
            $this->Image($logo, $imgX, $this->GetY(), $wMm, 0, $type);
            $this->Ln($hMm + 5);
        } catch (\Exception $e) {
            $this->Ln(3);
        }
    }

    private function renderHeader(array $data, float $pageW): void
    {
        // TCPDF handles UTF-8 natively — no iconv() needed.
        $this->setFont($this->fontFamily, '', $this->baseFontSize);

        $left  = __('Folio', 'protocolsmanager') . ': ' . ($data['prot_num'] ?? '') . '-' . date('dmY');
        $right = trim($data['city'] ?? '') . ' ' . self::formatDate((int)($data['date_format'] ?? 0));

        $this->Cell($pageW * 0.60, 6, $left,  0, 0, 'L');
        $this->Cell($pageW * 0.40, 6, $right, 0, 1, 'R');
        $this->Ln(2);

        $this->setFont($this->fontFamily, 'B', 14);
        $this->MultiCell($pageW, 8, $data['title'] ?? '', 0, 'C');
        $this->Ln(3);
    }

    private function renderUpperContent(array $data, float $pageW): void
    {
        $text = trim($data['upper_content'] ?? '');
        if ($text === '') {
            return;
        }
        $this->setFont($this->fontFamily, '', $this->baseFontSize);
        $this->MultiCell($pageW, 5, $this->nl($text), 0, 'L');
        $this->Ln(3);
    }

    /**
     * Renders the equipment table with dynamically calculated column widths.
     *
     * Algorithm:
     *  1. Measure minimum column width via GetStringWidth() for headers and
     *     all data cells, adding 3 mm padding.
     *  2. If total fits in $pageW, distribute surplus proportionally to text
     *     columns (all except '#').
     *  3. If it does not fit, reduce font size by 1 pt and retry (up to 4
     *     attempts, floor 5 pt).
     *  4. Row height adjusts per-row so multi-line cells expand uniformly
     *     using Rect() + SetXY() + MultiCell().
     *
     * The State column is always included and populated from the states_id
     * dropdown selection (GLPI states for GLPI rows; manual select for
     * custom rows).  The Comments column is always present so the printed
     * document has a writable annotation field even when no text is entered.
     */
    private function renderItemsTable(array $data, float $pageW): void
    {
        $number     = $data['number']     ?? [];
        $serialMode = (int)($data['serial_mode'] ?? 1);
        $stateNames = $data['state_name'] ?? [];

        // Column definitions — State is always present, Comments always last.
        if ($serialMode === 1) {
            $labels = [
                '#',
                __('Type'),
                __('Manufacturer'),
                __('Model'),
                __('Name'),
                __('State'),
                __('Serial number'),
                __('Inventory number'),
                __('Comments'),
            ];
            $keys = ['__no__', 'type', 'man', 'mod', 'name', 'state', 'serial', 'inv', 'comment'];
        } else {
            // serial_mode 2: merge serial + otherserial into one column.
            $labels = [
                '#',
                __('Type'),
                __('Manufacturer'),
                __('Model'),
                __('Name'),
                __('State'),
                __('Serial number'),
                __('Comments'),
            ];
            $keys = ['__no__', 'type', 'man', 'mod', 'name', 'state', 'serial', 'comment'];
        }

        // Build data rows.
        $lp   = 1;
        $rows = [];
        foreach ($number as $idx) {
            if ($serialMode === 2) {
                $sv     = $data['serial'][$idx] ?? '';
                $serial = ($sv !== '') ? $sv : ($data['otherserial'][$idx] ?? '');
            } else {
                $serial = $data['serial'][$idx] ?? '';
            }

            // Normalise empty optional fields to em-dash, matching the UI table.
            $emDash = '—'; // — UTF-8 em-dash, native in TCPDF Unicode mode
            $rows[] = [
                '__no__'  => (string)$lp++,
                'type'    => $data['type_name'][$idx]  ?? '',
                'man'     => ($data['man_name'][$idx]   ?? '') !== '' ? ($data['man_name'][$idx]   ?? '') : $emDash,
                'mod'     => ($data['mod_name'][$idx]   ?? '') !== '' ? ($data['mod_name'][$idx]   ?? '') : $emDash,
                'name'    => $data['item_name'][$idx]  ?? '',
                'state'   => ($stateNames[$idx]         ?? '') !== '' ? ($stateNames[$idx]         ?? '') : $emDash,
                'serial'  => $serial !== ''  ? $serial  : $emDash,
                'inv'     => ($data['otherserial'][$idx] ?? '') !== '' ? ($data['otherserial'][$idx] ?? '') : $emDash,
                'comment' => $data['comments'][$idx]   ?? '',
            ];
        }

        // Auto-sizing with font-size reduction fallback.
        $small  = max(5, $this->baseFontSize - 1);
        $widths = null;

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $widths = $this->calcColumnWidths($labels, $keys, $rows, $pageW, $small - $attempt);
            if ($widths !== null) {
                $small -= $attempt;
                break;
            }
        }

        if ($widths === null) {
            $n      = count($keys);
            $widths = array_fill(0, $n, $pageW / $n);
        }

        $hdrH = 6;
        $rowH = 5;

        // Header row.
        $this->setFont($this->fontFamily, 'B', $small);
        $this->setFillColor(220, 220, 220);
        foreach ($labels as $i => $label) {
            $this->Cell($widths[$i], $hdrH, $label, 1, 0, 'C', true);
        }
        $this->Ln();

        // Data rows.
        $this->setFont($this->fontFamily, '', $small);
        $this->setFillColor(255, 255, 255);

        foreach ($rows as $row) {
            $maxH = $rowH;
            foreach ($keys as $i => $key) {
                $sw        = $this->GetStringWidth($row[$key]);
                $cellInner = $widths[$i] - 2;
                if ($cellInner > 0 && $sw > $cellInner) {
                    $maxH = max($maxH, $rowH * ceil($sw / $cellInner));
                }
            }

            $startX = $this->GetX();
            $startY = $this->GetY();

            if ($startY + $maxH > $this->getPageHeight() - 22) {
                $this->AddPage();
                $startX = $this->GetX();
                $startY = $this->GetY();
            }

            // Two-pass rendering:
            //  Pass 1 — draw all borders at full $maxH (so borders align regardless
            //            of content height).
            //  Pass 2 — write text using Cell() (single-line) or MultiCell()
            //            (wrapped). TCPDF's 1mm setCellPadding provides the inner
            //            margin so we pass full $widths[$i] without manual offsets.

            // Pass 1: borders
            $curX = $startX;
            foreach ($keys as $i => $key) {
                $this->Rect($curX, $startY, $widths[$i], $maxH);
                $curX += $widths[$i];
            }

            // Pass 2: text
            $curX = $startX;
            foreach ($keys as $i => $key) {
                $align = ($key === '__no__') ? 'C' : 'L';
                $text  = $row[$key];
                $sw    = $this->GetStringWidth($text);
                $inner = $widths[$i] - 2.0; // approximate inner width (2×1mm padding)

                $this->SetXY($curX, $startY);

                if ($sw <= $inner) {
                    // Single line: use Cell() — reliable, never wraps unexpectedly.
                    $this->Cell($widths[$i], $maxH, $text, 0, 0, $align);
                } else {
                    // Multi-line: use MultiCell() with autopadding=false so TCPDF
                    // does not add extra padding on top of our setCellPadding(1).
                    $this->MultiCell(
                        $widths[$i], $rowH, $text,
                        0, $align, false, 1,
                        $curX, $startY,
                        true, 0, false, false // reseth, stretch, ishtml, autopadding=false
                    );
                }
                $curX += $widths[$i];
            }

            $this->SetXY($startX, $startY + $maxH);
        }
    }

    /**
     * Calculate column widths for a given font size.
     * Returns null if content does not fit at this size.
     *
     * @return float[]|null
     */
    private function calcColumnWidths(
        array $labels,
        array $keys,
        array $rows,
        float $pageW,
        int   $fontSize
    ): ?array {
        // 2mm total padding = 2 × setCellPadding(1) applied during rendering.
        $pad = 2.0;

        $this->setFont($this->fontFamily, 'B', $fontSize);
        $minW = [];
        foreach ($labels as $i => $label) {
            $minW[$i] = $this->GetStringWidth($label) + $pad;
        }
        // Ensure the # column is always wide enough to render row numbers.
        $minW[0] = max($minW[0], 8.0);

        $this->setFont($this->fontFamily, '', $fontSize);
        foreach ($rows as $row) {
            foreach ($keys as $i => $key) {
                $w = $this->GetStringWidth($row[$key]) + $pad;
                if ($w > $minW[$i]) {
                    $minW[$i] = $w;
                }
            }
        }

        if (array_sum($minW) > $pageW) {
            return null;
        }

        $surplus = $pageW - array_sum($minW);
        if ($surplus > 0 && count($minW) > 1) {
            $textTotal = array_sum($minW) - $minW[0];
            if ($textTotal > 0) {
                for ($i = 1; $i < count($minW); $i++) {
                    $minW[$i] += $surplus * ($minW[$i] / $textTotal);
                }
            }
        }

        return array_values($minW);
    }

    private function renderContent(array $data, float $pageW): void
    {
        $text = trim($data['content'] ?? '');
        if ($text === '') {
            return;
        }
        $this->Ln(3);
        $this->setFont($this->fontFamily, '', $this->baseFontSize);
        $this->MultiCell($pageW, 5, $this->nl($text), 0, 'L');
        $this->Ln(5);
    }

    /**
     * Two side-by-side signature boxes: Administrator (left) and User (right).
     */
    private function renderSignature(array $data, float $pageW): void
    {
        $halfW = $pageW / 2;
        $boxH  = 22;

        $this->setFont($this->fontFamily, 'B', $this->baseFontSize);
        $this->Cell($halfW, 6, __('Administrator', 'protocolsmanager') . ':', 'B', 0, 'L');
        $this->Cell($halfW, 6, __('User', 'protocolsmanager') . ':',          'B', 1, 'L');

        $adminText = ((int)($data['author_state'] ?? 1) === 2)
            ? ($data['author_name'] ?? '')
            : ($data['author']      ?? '');
        $ownerText = $data['owner'] ?? '';

        $startX = $this->GetX();
        $startY = $this->GetY();

        $this->Rect($startX,          $startY, $halfW, $boxH);
        $this->Rect($startX + $halfW, $startY, $halfW, $boxH);

        $this->setFont($this->fontFamily, '', $this->baseFontSize);

        // Write signature names inside boxes using Cell() — single-line, reliable.
        if (!empty($adminText)) {
            $this->SetXY($startX, $startY);
            $this->Cell($halfW, 8, $adminText, 0, 0, 'L');
        }
        if (!empty($ownerText)) {
            $this->SetXY($startX + $halfW, $startY);
            $this->Cell($halfW, 8, $ownerText, 0, 0, 'L');
        }

        $this->SetY($startY + $boxH + 3);
    }

    // -----------------------------------------------------------------------
    // Date formatting
    // -----------------------------------------------------------------------

    /**
     * Format today's date according to the template's date_format setting.
     *
     * date_format = 0 → numeric:  16.03.2026
     * date_format = 1 → text:     uses PHP IntlDateFormatter with the GLPI
     *                             session locale ($_SESSION['glpilanguage']).
     *                             Falls back to numeric if the intl extension
     *                             is not loaded.
     *
     * No plugin-owned month-name strings needed — the OS ICU data covers all
     * locales that GLPI itself supports.
     */
    private static function formatDate(int $mode): string
    {
        if ($mode !== 1 || !extension_loaded('intl')) {
            return date('d.m.Y');
        }

        // Map GLPI locale (e.g. 'es_MX', 'fr_FR') to ICU locale string.
        // IntlDateFormatter accepts both 'es_MX' and 'es' formats.
        $locale = $_SESSION['glpilanguage'] ?? 'en_US';

        // LONG date pattern gives e.g. "16 de marzo de 2026" in es_MX,
        // "16 mars 2026" in fr_FR, "March 16, 2026" in en_US.
        $fmt = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::LONG,  // date style
            \IntlDateFormatter::NONE,  // time style — none
            null,                       // timezone — use default
            null,                       // calendar — Gregorian
            null                        // pattern — use locale default
        );

        $result = $fmt->format(new \DateTime());
        return ($result !== false) ? $result : date('d.m.Y');
    }

    // -----------------------------------------------------------------------
    // Utilities
    // -----------------------------------------------------------------------

    /**
     * Convert literal '\n' sequences (backslash + n as stored in DB)
     * to real PHP newline characters for multi-line rendering.
     * Note: TCPDF handles UTF-8 natively — no iconv() conversion needed.
     */
    private function nl(string $text): string
    {
        return str_replace('\\n', "\n", $text);
    }
}

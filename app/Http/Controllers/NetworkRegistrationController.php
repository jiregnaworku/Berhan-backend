<?php

namespace App\Http\Controllers;

use App\Models\NetworkRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NetworkRegistrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = NetworkRegistration::query()->latest('incident_date')->latest();

        if ($request->user()?->role === 'ambassador') {
            $query->where('created_by', $request->user()->username);
        }

        if ($request->filled('month')) {
            $month = $request->string('month')->toString();
            $monthDate = \DateTimeImmutable::createFromFormat('!Y-m', $month);
            if (!$monthDate || $monthDate->format('Y-m') !== $month) {
                return response()->json(['message' => 'Month must use YYYY-MM format.'], 422);
            }
            $query->whereBetween('incident_date', [
                $monthDate->format('Y-m-01'),
                $monthDate->format('Y-m-t'),
            ]);
        }

        return response()->json(['registrations' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedData($request);

        $branchNames = $request->input('branch_names', []);
        if (empty($branchNames) && !empty($validated['branch_name'])) {
            $branchNames = [$validated['branch_name']];
        }

        if (empty($branchNames)) {
            return response()->json(['message' => 'Please select at least one branch.'], 422);
        }

        $records = [];
        foreach ($branchNames as $branchName) {
            $records[] = NetworkRegistration::create([
                'branch_name' => $branchName,
                'reason' => $validated['reason'],
                'remark' => $validated['remark'] ?? null,
                'ethio_telecom_side' => $validated['ethio_telecom_side'] ?? null,
                'ethio_telecom_frequency' => $validated['ethio_telecom_frequency'] ?? null,
                'elpha_side' => $validated['elpha_side'] ?? null,
                'elpha_frequency' => $validated['elpha_frequency'] ?? null,
                'incident_date' => $validated['incident_date'],
                'created_by' => $request->user()->username,
            ]);
        }

        return response()->json([
            'registration' => $records[0],
            'registrations' => $records,
            'count' => count($records),
        ], 201);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];
        $monthDate = \DateTimeImmutable::createFromFormat('!Y-m', $month);
        $registrations = NetworkRegistration::query()
            ->where('created_by', $request->user()->username)
            ->whereBetween('incident_date', [$monthDate->format('Y-m-01'), $monthDate->format('Y-m-t')])
            ->orderBy('branch_name')
            ->orderBy('incident_date')
            ->get();
        $rows = $registrations->map(fn (NetworkRegistration $registration): array => [
            $registration->branch_name,
            $registration->incident_date->format('Y-m-d'),
            $registration->reason,
            $registration->ethio_telecom_side ?: '-',
            $registration->ethio_telecom_frequency ?: '-',
            $registration->elpha_side ?: '-',
            $registration->elpha_frequency ?: '-',
            $registration->remark ?: '-',
            $registration->created_by,
        ])->all();
        $branches = [];

        foreach ($registrations as $registration) {
            $branch = $branches[$registration->branch_name] ?? [
                'ethioTelecom' => 0,
                'elpa' => 0,
                'powerOutage' => 0,
                'remarks' => [],
            ];
            if ($registration->reason === 'Ethio Telecom') $branch['ethioTelecom']++;
            if ($registration->reason === 'ELPA') $branch['elpa']++;
            $details = trim((string) $registration->remark);
            $date = $registration->incident_date->format('F j, Y');
            $branch['remarks'][] = trim($registration->reason . ($details ? ' - ' . $details : '') . ' - ' . $date);
            $branches[$registration->branch_name] = $branch;
        }

        $rows = [];
        $number = 1;
        foreach ($branches as $branchName => $branch) {
            $rows[] = [
                $number++,
                $branchName,
                $branch['ethioTelecom'] ?: '–',
                $branch['elpa'] ?: '–',
                $branch['powerOutage'] ?: '–',
                implode('; ', array_unique($branch['remarks'])),
            ];
        }

        $directory = storage_path('app/network-reports');
        if (! is_dir($directory)) mkdir($directory, 0755, true);
        $file = tempnam($directory, 'network-report-');
        file_put_contents($file, $this->networkPdf(
            $monthDate,
            $request->user()->name ?: $request->user()->username,
            $rows,
        ));

        $ambassadorName = preg_replace('/[^A-Za-z0-9]+/', '_', trim($request->user()->name ?: $request->user()->username));

        return response()->download(
            $file,
            sprintf('%s_%s_Network_Report.pdf', trim($ambassadorName, '_'), $monthDate->format('F_Y')),
            ['Content-Type' => 'application/pdf'],
        )->deleteFileAfterSend(true);
    }

    private function networkPdf(\DateTimeImmutable $monthDate, string $ambassador, array $rows): string
    {
        $headers = ['Branch', 'Date', 'Reason', 'Telecom side', 'Telecom frequency', 'ELPA side', 'ELPA frequency', 'Remark', 'Uploaded by'];
        $widths = [92, 54, 62, 70, 82, 55, 75, 170, 72];
        $pageWidth = 842;
        $pageHeight = 595;
        $left = 24;
        $top = 560;
        $pages = [];
        $content = '';
        $y = $top;
        $drawHeader = function () use (&$content, &$y, $left, $headers, $widths, $monthDate, $ambassador): void {
            $content .= "BT /F1 15 Tf {$left} {$y} Td (NETWORK REGISTRATION REPORT) Tj ET\n";
            $y -= 20;
            $content .= "BT /F1 9 Tf {$left} {$y} Td (" . $this->pdfEscape('Month: ' . $monthDate->format('F Y') . '    Uploaded by: ' . $ambassador) . ") Tj ET\n";
            $y -= 18;
            $x = $left;
            foreach ($headers as $index => $header) {
                $content .= "BT /F1 6 Tf {$x} {$y} Td (" . $this->pdfEscape($header) . ") Tj ET\n";
                $x += $widths[$index];
            }
            $y -= 18;
        };
        $finishPage = function () use (&$pages, &$content): void {
            $pages[] = $content;
            $content = '';
        };
        $drawHeader();
        foreach ($rows as $number => $row) {
            if ($y < 40) {
                $finishPage();
                $y = $top;
                $drawHeader();
            }
            $x = $left;
            foreach ($row as $index => $value) {
                $limit = max(8, (int) floor($widths[$index] / 4.2));
                $text = substr(preg_replace('/[^\x20-\x7E]/', '', (string) $value), 0, $limit);
                $content .= "BT /F1 6 Tf {$x} {$y} Td (" . $this->pdfEscape($text) . ") Tj ET\n";
                $x += $widths[$index];
            }
            $y -= 16;
        }
        $finishPage();

        $objects = [];
        $pdf = "%PDF-1.4\n";
        $addObject = function (string $body) use (&$objects): int {
            $objects[] = $body;
            return count($objects);
        };
        $font = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $pageRoot = $addObject('<< /Type /Pages /Kids [] /Count 0 >>');
        $contentObjects = [];
        foreach ($pages as $page) {
            $contentObjects[] = $addObject("<< /Length " . strlen($page) . " >>\nstream\n{$page}endstream");
        }
        $kids = [];
        foreach ($contentObjects as $object) {
            $kids[] = $addObject("<< /Type /Page /Parent {$pageRoot} 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 {$font} 0 R >> >> /Contents {$object} 0 R >>") . ' 0 R';
        }
        $objects[$pageRoot - 1] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $catalog = $addObject("<< /Type /Catalog /Pages {$pageRoot} 0 R >>");
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalog} 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
    }

    private function xmlText(string|int $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function textCell(string $reference, string|int $value, int $style = 0): string
    {
        return '<c r="' . $reference . '" t="inlineStr" s="' . $style . '"><is><t>' . $this->xmlText($value) . '</t></is></c>';
    }

    private function numberCell(string $reference, int $value, int $style = 0): string
    {
        return '<c r="' . $reference . '" s="' . $style . '"><v>' . $value . '</v></c>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Network Status" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="D9EAF7"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment horizontal="center"/></xf><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleMedium9"/></styleSheet>';
    }

    private function sheetXml(\DateTimeImmutable $monthDate, string $ambassador, array $rows, int $total): string
    {
        $monthLabel = strtoupper($monthDate->format('F 1–t, Y'));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:F' . (count($rows) + 10) . '"/><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="15"/><sheetData>';
        $xml .= '<row r="1"><c r="A1" s="1" t="inlineStr"><is><t>BRANCH NETWORK ASSESSMENT MONTHLY REPORT</t></is></c></row>';
        $xml .= '<row r="3">' . $this->textCell('A3', 'Date:', 1) . $this->textCell('B3', $monthLabel) . '</row>';
        $xml .= '<row r="4">' . $this->textCell('A4', 'Ambassador Name:', 1) . $this->textCell('B4', $ambassador) . '</row>';
        $xml .= '<row r="6">' . $this->textCell('A6', 'No.', 2) . $this->textCell('B6', 'Branch Name', 2) . $this->textCell('C6', "Ethio Telecom Side\nFrequency", 2) . $this->textCell('D6', "ELPA Side\nFrequency", 2) . $this->textCell('E6', "Power Outage\nFrequency", 2) . $this->textCell('F6', 'Remark', 2) . '</row>';
        $rowNumber = 7;
        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowNumber . '">' . $this->numberCell('A' . $rowNumber, (int) $row[0], 3) . $this->textCell('B' . $rowNumber, $row[1]) . $this->textCell('C' . $rowNumber, $row[2], 3) . $this->textCell('D' . $rowNumber, $row[3], 3) . $this->textCell('E' . $rowNumber, $row[4], 3) . $this->textCell('F' . $rowNumber, $row[5]) . '</row>';
            $rowNumber++;
        }
        $xml .= '<row r="' . ($rowNumber + 1) . '">' . $this->textCell('A' . ($rowNumber + 1), 'Total', 1) . $this->numberCell('B' . ($rowNumber + 1), count($rows), 1) . '</row>';
        $xml .= '<row r="' . ($rowNumber + 3) . '">' . $this->textCell('A' . ($rowNumber + 3), 'Total recorded outage incidents: ' . $total, 1) . '</row>';
        $xml .= '</sheetData><mergeCells count="3"><mergeCell ref="A1:F1"/><mergeCell ref="A' . ($rowNumber + 1) . ':B' . ($rowNumber + 1) . '"/><mergeCell ref="A' . ($rowNumber + 3) . ':F' . ($rowNumber + 3) . '"/></mergeCells><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="24" customWidth="1"/><col min="3" max="5" width="22" customWidth="1"/><col min="6" max="6" width="48" customWidth="1"/></cols></worksheet>';
        return $xml;
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'branch_name' => ['nullable', 'string', 'max:120'],
            'branch_names' => ['nullable', 'array'],
            'branch_names.*' => ['string', 'max:120'],
            'reason' => ['required', 'in:ELPA,Ethio Telecom'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'ethio_telecom_side' => ['nullable', 'string', 'max:120'],
            'ethio_telecom_frequency' => ['nullable', 'string', 'max:120'],
            'elpha_side' => ['nullable', 'string', 'max:120'],
            'elpha_frequency' => ['nullable', 'string', 'max:120'],
            'incident_date' => ['required', 'date'],
        ]);
    }
}

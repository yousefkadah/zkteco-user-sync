<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

class TemplateController extends Controller
{
    /**
     * Download a starter user list.
     *
     * We emit a CSV (not XLSX): the bundled runtime PHP ships without the
     * xmlwriter/xmlreader extensions, so PhpSpreadsheet's Xlsx writer throws
     * "Class \"XMLWriter\" not found" and the download 500s. CSV needs none of
     * that, opens cleanly in Excel/Sheets, and is the format the importer reads
     * most reliably.
     */
    public function download(): Response
    {
        $rows = [
            ['user_id', 'name', 'password', 'card_number', 'privilege'],
            ['1001', 'Dana Cohen', '1234', '', 'user'],
            ['1002', 'Yossi Levi', '5678', '0009876543', 'user'],
            ['1', 'Site Admin', '9999', '', 'admin'],
        ];

        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM so Excel opens the file as UTF-8 (keeps Hebrew names intact
        // when the user fills the template in and re-saves it).
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="zkteco-users-template.csv"',
        ]);
    }
}

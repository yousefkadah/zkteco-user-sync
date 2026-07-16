<?php

declare(strict_types=1);

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpreadsheetFactory
{
    /**
     * Write rows to a temporary .xlsx file and return its path.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    public static function xlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'zk_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * Write raw bytes to a temporary .csv file and return its path. Takes a raw
     * string rather than rows so tests can pin down BOMs, delimiters and line
     * endings exactly as a user's exported file would have them.
     */
    public static function csvRaw(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zk_test_').'.csv';
        file_put_contents($path, $contents);

        return $path;
    }
}

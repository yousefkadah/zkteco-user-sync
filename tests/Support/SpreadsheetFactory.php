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
}

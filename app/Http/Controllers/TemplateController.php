<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemplateController extends Controller
{
    public function download(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Users');

        $sheet->fromArray([
            ['user_id', 'name', 'password', 'card_number', 'privilege'],
            ['1001', 'Dana Cohen', '1234', '', 'user'],
            ['1002', 'Yossi Levi', '5678', '0009876543', 'user'],
            ['1', 'Site Admin', '9999', '', 'admin'],
        ], null, 'A1');

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'zk_template_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()
            ->download($tempPath, 'zkteco-users-template.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }
}

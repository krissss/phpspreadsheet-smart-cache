<?php

namespace Tests\Benchmark;

use Generator;
use Kriss\SpreadsheetSmartCache\SpreadsheetSmartCache;
use PhpBench\Attributes as Bench;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

final class SmartCacheBench
{
    #[Bench\ParamProviders(['provideCells'])]
    public function benchMemory(array $params): void
    {
        $this->doSpreadsheet($params['cells']);
    }

    #[Bench\BeforeMethods(['setUpCacheSetting'])]
    #[Bench\ParamProviders(['provideCells'])]
    public function benchSmartCache(array $params): void
    {
        $this->doSpreadsheet($params['cells']);
    }

    public function setUpCacheSetting()
    {
        $cache = new SpreadsheetSmartCache(__DIR__ . '/cache');
        Settings::setCache($cache);
    }

    public function provideCells(): Generator
    {
        $cases = [
            'A1:Z1000',
            'A1:Z10000',
            'A1:Z20000',
            'A1:Z30000',
//            'A1:Z50000',
//            'A1:CZ10000',
        ];
        foreach ($cases as $case) {
            yield $case => [ 'cells' => $case ];
        }
    }

    private function doSpreadsheet(string $cellCoordinate): void
    {
        $border = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet();
        $sheet->getStyle($cellCoordinate)->applyFromArray($border);

        unset($sheet, $spreadsheet);
    }
}

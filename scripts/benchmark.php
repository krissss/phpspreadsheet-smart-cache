<?php

require __DIR__ . '/../vendor/autoload.php';

use Kriss\SpreadsheetSmartCache\SpreadsheetSmartCache;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

$start = count($argv) === 1;

function write_line(string $msg)
{
    echo date('Y-m-d H:i:s') . ': ' . $msg . PHP_EOL;
}

if ($start) {
    $data = [
        [
            'cells' => 'A1:Z1000',
        ],
        [
            'cells' => 'A1:Z10000',
        ],
        [
            'cells' => 'A1:Z20000',
        ],
        [
            'cells' => 'A1:Z30000',
        ],
        [
            'cells' => 'A1:CZ10000',
        ],
    ];
    foreach ($data as &$item) {
        $command = 'php ' . __FILE__ . ' --cells=' . $item['cells'];
        write_line('run: ' . $command);
        $item['result'] = json_decode(shell_exec($command), true);

        $command = 'php ' . __FILE__ . ' --cells=' . $item['cells'] . ' --set-cache';
        write_line('run: ' . $command);
        $item['cache_result'] = json_decode(shell_exec($command), true);
    }
    unset($item);

    write_line('result');
    $table[] = '| cells | type | ts | memory_peak |';
    $fnGetTableData = fn(array $item, string $key) => sprintf('| %s | %s | %.6f | %.2f |', $item['cells'], $key, $item[$key]['ts'], $item[$key]['memory_peak']);
    foreach ($data as $item) {
        $table[] = $fnGetTableData($item, 'result');
        $table[] = $fnGetTableData($item, 'cache_result');
    }
    echo implode(PHP_EOL, $table) . PHP_EOL;
    return;
}

$cellCoordinate = 'A1:Z10000';
$setCache = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--cells')) {
        $cellCoordinate = substr($arg, strlen('--cells='));
    } elseif (str_starts_with($arg, '--set-cache')) {
        $setCache = true;
    }
}

function do_spreadsheet(string $cellCoordinate): void
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
}

function set_cache(): void
{
    $cache = new SpreadsheetSmartCache(__DIR__ . '/cache');
    Settings::setCache($cache);
}

$start = microtime(true);

if ($setCache) {
    set_cache();
}
do_spreadsheet($cellCoordinate);

$time = round(microtime(true) - $start, 6);

echo json_encode([
    'ts' => $time,
    'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024,
]);

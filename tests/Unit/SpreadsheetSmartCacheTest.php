<?php

use Kriss\SpreadsheetSmartCache\SpreadsheetSmartCache;

test('simple crud', function () {
    $path = runtime_path('spreadsheet_smart_cache');
    $cache = new SpreadsheetSmartCache($path);

    // 初始化后文件目录存在
    expect(file_exists($path))->toBeTrue();

    // 测试简单的读写删
    $key = str_random();
    $value = str_random();
    // create
    $cache->set($key, $value);
    expect($cache->get($key))->toBe($value)
        ->and($cache->has($key))->toBeTrue();
    // update
    $value = str_random();
    $cache->set($key, $value);
    expect($cache->get($key))->toBe($value);
    // delete
    $cache->delete($key);
    expect($cache->get($key))->toBeNull()
        ->and($cache->has($key))->toBeFalse();

    // 用完之后，没有文件残留
    $cache->__destruct();
    expect(file_exists($path))->toBeFalse();
});

test('memory control', function () {
    $path = runtime_path('spreadsheet_smart_cache');
    $cache = new SpreadsheetSmartCache($path);

    $memoryStart = memory_get_peak_usage(true) / 1024;

    $length = rand(10000, 20000);
    for ($i = 0; $i < $length; $i++) {
        $key = 'key1-' . $i;
        $value = str_repeat('a', random_int(500, 1024));
        $cache->set($key, $value);
    }

    $memoryEnd1 = memory_get_peak_usage(true) / 1024;
    $used1 = $memoryEnd1 - $memoryStart;

    $length = rand(10000, 20000);
    for ($i = 0; $i < $length; $i++) {
        $key = 'key2-' . $i;
        $value = str_repeat('a', random_int(1025, 3000));
        $cache->set($key, $value);
    }

    $memoryEnd2 = memory_get_peak_usage(true) / 1024;
    $used2 = $memoryEnd2 - $memoryEnd1;
    expect($used2)->toBeLessThanOrEqual($used1);

    $length = rand(10, 20);
    for ($i = 0; $i < $length; $i++) {
        $key = 'key3-' . $i;
        $value = str_repeat('a', random_int(5000, 6000));
        $cache->set($key, $value);
    }

    $memoryEnd3 = memory_get_peak_usage(true) / 1024;
    $used3 = $memoryEnd3 - $memoryEnd2;
    expect($used3)->toBeLessThanOrEqual($used1);
});

test('value size change', function () {
    $path = runtime_path('spreadsheet_smart_cache');
    $cache = new SpreadsheetSmartCache($path);

    $key = str_random();

    $value = str_repeat('a', random_int(500, 1024));
    $cache->set($key, $value);

    expect($cache->get($key))->toBe($value);

    $value = str_repeat('a', random_int(2048, 3000));
    $cache->set($key, $value);

    expect($cache->get($key))->toBe($value);
});

# 简介

一个专为 [PHPSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) 设计的智能缓存机制，用于优化和平衡大文件处理时的内存使用和性能问题。

> 简体中文(zh-cn) | [English](./README_en.md)

# 安装

```bash
composer require kriss/spreadsheet-smart-cache
```

# 使用方法

详细介绍请参见官方文档：[PhpSpreadsheet Memory Saving](https://phpspreadsheet.readthedocs.io/en/latest/topics/memory_saving/)

```php
$yourLocalPath = __DIR__ . '/' . uniqid('spreadsheet_smart_cache_');
$cache = new \Kriss\SpreadsheetSmartCache\SpreadsheetSmartCache($yourLocalPath);

\PhpOffice\PhpSpreadsheet\Settings::setCache($cache);
```

> **注意**：为避免多并发情况下缓存目录冲突，`$yourLocalPath` 建议应该执行时都不相同。

# 设计思路与优势

### 为什么不使用 APCu、Redis、MemCache？

- **APCu** 仍然占用服务器内存，与直接使用内存几乎没有区别，且 PhpSpreadsheet 本身并无跨进程缓存共享的必要。
- **Redis/MemCache** 只是在将内存压力转移到外部服务。当处理大文件及高并发时，极易导致外部缓存服务内存压力过大、性能下降。

### 为什么推荐文件缓存？

- 处理超大、多个并发的电子表格时，文件缓存几乎可以认为空间无限，更适合大规模数据处理场景。

### 为什么不直接用常规文件缓存（如 Symfony/cache 或 illuminate/cache 的文件缓存）？

- 这些方案通常是“一个 key 一个文件”的存储模式。
- 处理大量单元格时，会产生海量零碎小文件，频繁读写极大影响性能。

### SmartCache 的优化点？

- **批量存储**：多个 Cells 共用单一文件缓存，减少文件操作。
- **分块存储**：采用 ChunkSize 分块，提升空间利用效率，减少磁盘浪费。
- **高效读写**：通过文件 seek 实现偏移读写，避免大量小文件操作。
- **标记与统一处理**：删除等操作时，仅标记删除位，最终直接删除整个目录。

# 性能测试

测试机器：macos M4 16G SSD

php: 8.2

phpoffice/phpspreadsheet: 5.0

| 单元格范围      | 是否使用 SmartCache | 耗时（秒）     | memory_peak（M） |
|------------|-----------------|-----------|----------------|
| A1:Z1000   | N               | 0.557178  | 18.00          |
| A1:Z1000   | Y               | 0.893424  | 12.00          |
| A1:Z10000  | N               | 5.465772  | 120.00         |
| A1:Z10000  | Y               | 8.861701  | 48.00          |
| A1:Z20000  | N               | 11.333312 | 236.00         |
| A1:Z20000  | Y               | 18.627746 | 90.00          |
| A1:Z30000  | N               | 16.654778 | 380.00         |
| A1:Z30000  | Y               | 27.586086 | 152.00         |
| A1:CZ10000 | N               | 21.779006 | 468.00         |
| A1:CZ10000 | Y               | 36.568663 | 174.00         |

可通过 [scripts/benchmark.php](scripts/benchmark.php) 脚本测试实际性能表现。

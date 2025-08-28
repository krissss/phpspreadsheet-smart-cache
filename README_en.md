# Introduction

An intelligent caching mechanism designed for [PHPSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) to optimize and balance memory usage and performance issues when processing large files.

> [简体中文(zh-cn)](./README.md) | English

# Installation

```bash
composer require kriss/spreadsheet-smart-cache
```

# Usage

For detailed instructions, please refer to the official documentation: [PhpSpreadsheet Memory Saving](https://phpspreadsheet.readthedocs.io/en/latest/topics/memory_saving/)

```php
$yourLocalPath = __DIR__ . '/' . uniqid('spreadsheet_smart_cache_');
$cache = new \Kriss\SpreadsheetSmartCache\SpreadsheetSmartCache($yourLocalPath);

\PhpOffice\PhpSpreadsheet\Settings::setCache($cache);
```

> **Note**: To avoid cache directory conflicts in multi-concurrent scenarios, `$yourLocalPath` should be different each time it's executed.

# Design Concepts and Advantages

### Why not use APCu, Redis, or MemCache?

- **APCu** still occupies server memory, which makes little difference compared to using memory directly. Moreover, PhpSpreadsheet itself has no need for cross-process cache sharing.
- **Redis/MemCache** simply shifts memory pressure to external services. When processing large files with high concurrency, this can easily cause excessive memory pressure and performance degradation on external cache services.

### Why recommend file caching?

- When processing extremely large spreadsheets or multiple concurrent ones, file caching can be considered virtually unlimited in space, making it more suitable for large-scale data processing scenarios.

### Why not use conventional file caching (e.g., Symfony/cache or illuminate/cache file caching)?

- These solutions typically follow a "one key, one file" storage pattern.
- With large numbers of cells, this generates massive amounts of small files, and frequent read/write operations severely impact performance.

### What are SmartCache's optimizations?

- **Batch Storage**: Multiple cells share a single cache file, reducing file operations.
- **Chunked Storage**: Uses ChunkSize partitioning to improve space utilization efficiency and reduce disk waste.
- **Efficient Read/Write**: Implements offset reading/writing through file seeking, avoiding massive small file operations.
- **Tagging and Unified Processing**: For operations like deletion, only mark the delete flag, and finally delete the entire directory directly.

# Performance Testing

Test machine: macOS M4 16G SSD

PHP: 8.2

phpoffice/phpspreadsheet: 5.0

| Cell Range     | Using SmartCache | Time (seconds) | memory_peak (M) |
|----------------|------------------|----------------|-----------------|
| A1:Z1000       | N                | 0.557178       | 18.00           |
| A1:Z1000       | Y                | 0.893424       | 12.00           |
| A1:Z10000      | N                | 5.465772       | 120.00          |
| A1:Z10000      | Y                | 8.861701       | 48.00           |
| A1:Z20000      | N                | 11.333312      | 236.00          |
| A1:Z20000      | Y                | 18.627746      | 90.00           |
| A1:Z30000      | N                | 16.654778      | 380.00          |
| A1:Z30000      | Y                | 27.586086      | 152.00          |
| A1:CZ10000     | N                | 21.779006      | 468.00          |
| A1:CZ10000     | Y                | 36.568663      | 174.00          |

You can test actual performance through the [scripts/benchmark.php](scripts/benchmark.php) script.
# 安装

```bash
composer require kriss/spreadsheet-smart-cache
```

# 使用

参见：https://phpspreadsheet.readthedocs.io/en/latest/topics/memory_saving/

```php
$yourLocalPath = __DIR__ . '/' . uniqid('spreadsheet_smart_cache_');
$cache = new \Kriss\SpreadsheetSmartCache\SpreadsheetSmartCache($yourLocalPath);

\PhpOffice\PhpSpreadsheet\Settings::setCache($cache);
```

*注意：为了防止在多并发情况下同时使用到相同的缓存目录，$yourLocalPath 应该每次执行时都是不一样的*

# 思路

> 为什么不使用 APCu、Redis、MemCache？

APCu 仍然占用服务器内存，跟直接使用几乎没什么差别，除了进程共享（但是貌似 Spreadsheet 使用时没有缓存共享的意义吧）

Redis 和 MemCache 只是把内存的使用转嫁到了别的服务，如果 Spreadsheet 使用到的内存比较大时，Redis 和 MemCache 内存压力会比较大，
尤其是在多并发同时处理多个大文件时，几乎会压垮外部服务

> 为什么应该使用文件缓存？

因为在多并发处理多个超大 Spreadsheet 时，只有文件缓存几乎是无限空间的存在

> 为什么不直接使用简单的文件缓存（比如 Symfony/cache 或 illuminate/cache 中提供的文件缓存）？

因为他们都是基于一个 key 一个文件的存储方案

当 Spreadsheet 在处理超多的单元格的时候，快速的读写零碎的小文件会造成极大的性能问题

> SmartCache 是如何解决超多零碎小文件的性能问题的？

将多个 Cells 共用单个文件进行缓存，然后提供文件 seek 机制进行偏移读写

> SmartCache 的其他优化点

由于 Spreadsheet 并不关心 TTL，因此在处理缓存时可以忽略 TTL 的处理，这也使得我们可以在需要做删除操作时，无需实际处理相关缓存文件，仅在最终处理完整个 Spreadsheet 后将整个缓存目录删除即可。

为了节省磁盘空间，在进行偏移机制处理时，我们进行了 ChunkSize 分块，以达到尽可能不浪费太多空间。

# 开发

[benchmark.php](scripts/benchmark.php) 脚本可以用来看实际效果

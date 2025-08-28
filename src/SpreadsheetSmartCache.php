<?php

namespace Kriss\SpreadsheetSmartCache;

use PhpOffice\PhpSpreadsheet\Collection\Cells;
use Psr\SimpleCache\CacheInterface;

final class SpreadsheetSmartCache implements CacheInterface
{
    private array $chunkList;

    public function __construct(
        private readonly string $localDir,
        ?array                  $chunkList = null,
        private readonly bool   $autoClear = true,
    )
    {
        $this->ensureDirectoryExists($this->localDir);

        $this->chunkList = $chunkList ?? [1024, 2048, 3072, 4096, 5120];

        if ($this->autoClear) {
            register_shutdown_function(fn() => $this->clear());
        }
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * @var array<int, resource>
     */
    private array $resources = [];

    private function getResource(int $chunkSize)
    {
        if (!isset($this->resources[$chunkSize])) {
            $this->resources[$chunkSize] = fopen($this->localDir . '/' . $chunkSize . '.bin', 'w+');
        }
        return $this->resources[$chunkSize];
    }

    private array $nextPos = [];

    private function getNextPos(int $chunkSize): int
    {
        if (!isset($this->nextPos[$chunkSize])) {
            $this->nextPos[$chunkSize] = 0;
        }
        return $this->nextPos[$chunkSize];
    }

    private function setNextPos(int $chunkSize): void
    {
        $this->nextPos[$chunkSize] += $chunkSize + 1;
    }

    private array $keyPos = [];

    private function setKeyPosByChunkSize(int $chunkSize, string $key, int $position): void
    {
        $this->keyPos[$chunkSize][$key] = $position;
    }

    private function getChunkSizeByKey(string $key): ?int
    {
        foreach ($this->keyPos as $chunkSize => $keys) {
            if (isset($keys[$key])) {
                return $chunkSize;
            }
        }
        return null;
    }

    private function getKeyPosFromChunkSize(int $chunkSize, string $key): ?int
    {
        return $this->keyPos[$chunkSize][$key] ?? null;
    }

    private function deleteFromChunkSize(string $key): void
    {
        $chunkSize = $this->getChunkSizeByKey($key);
        if (!$chunkSize) {
            return;
        }
        unset($this->keyPos[$chunkSize][$key]);
        // 不真实去删掉相关内容
    }

    private function isExistInChunkSize(string $key): bool
    {
        return !!$this->getChunkSizeByKey($key);
    }

    private array $singleFiles = [];

    private function writeToSingleFile(string $key, string $value): void
    {
        file_put_contents($this->localDir . '/' . $key, $value);
        $this->singleFiles[$key] = 1;
    }

    private function readFromSingleFile(string $key): ?string
    {
        return file_get_contents($this->localDir . '/' . $key);
    }

    private function deleteFromSingleFile(string $key): void
    {
        unset($this->singleFiles[$key]);
        // 不做实际操作
    }

    private function isExistInSingleFile(string $key): bool
    {
        return isset($this->singleFiles[$key]);
    }

    private function normalizeKey(string $key): string
    {
        /**
         * 因为 key 会被作为 keyPos 的键存在于内存中
         * 为了进一步节省内存，因此进行一些处理
         * @see Cells::getUniqueID()
         */
        return str_replace('phpspreadsheet.', '', $key);
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null): bool
    {
        $key = $this->normalizeKey($key);
        $value = serialize($value);
        $length = strlen($value);

        $chunkSize = null;
        foreach ($this->chunkList as $limitSize) {
            if ($length <= $limitSize) {
                $chunkSize = $limitSize;
                break;
            }
        }

        $oldChunkSize = $this->getChunkSizeByKey($key);
        if ($oldChunkSize !== null && $oldChunkSize !== $chunkSize) {
            $this->deleteFromChunkSize($key);
        }
        if ($this->isExistInSingleFile($key) && $chunkSize !== null) {
            $this->deleteFromSingleFile($key);
        }

        if ($chunkSize === null) {
            $this->writeToSingleFile($key, $value);
        } else {
            $resource = $this->getResource($chunkSize);
            $position = $this->getKeyPosFromChunkSize($chunkSize, $key);
            if ($position === null) {
                $position = $this->getNextPos($chunkSize);
                $this->setNextPos($chunkSize);
                $this->setKeyPosByChunkSize($chunkSize, $key, $position);
            }
            fseek($resource, $position);
            fwrite($resource, str_pad($value, $chunkSize));
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null): mixed
    {
        $key = $this->normalizeKey($key);
        $value = null;
        $chunkSize = $this->getChunkSizeByKey($key);
        if ($chunkSize !== null) {
            $resource = $this->getResource($chunkSize);
            $position = $this->getKeyPosFromChunkSize($chunkSize, $key);
            fseek($resource, $position);
            $value = rtrim(fread($resource, $chunkSize));
        } elseif ($this->isExistInSingleFile($key)) {
            $value = $this->readFromSingleFile($key);
        }

        return $value ? unserialize($value) : $default;
    }

    /**
     * @inheritDoc
     */
    public function delete($key): bool
    {
        $key = $this->normalizeKey($key);
        $this->deleteFromChunkSize($key);
        $this->deleteFromSingleFile($key);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        if (is_dir($this->localDir)) {
            $files = scandir($this->localDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                unlink($this->localDir . '/' . $file);
            }
            rmdir($this->localDir);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $this->get($key, $default);
        }
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function has($key): bool
    {
        $key = $this->normalizeKey($key);
        return $this->isExistInChunkSize($key) || $this->isExistInSingleFile($key);
    }

    public function __destruct()
    {
        if ($this->autoClear) {
            $this->clear();
        }
        foreach ($this->resources as $resource) {
            fclose($resource);
        }
        $this->resources = [];
    }
}

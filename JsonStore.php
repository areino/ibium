<?php
declare(strict_types=1);

class JsonStore
{
    private static string $dataDir = '';

    public static function init(string $dataDir): void
    {
        self::$dataDir = rtrim($dataDir, '/\\');
    }

    public static function read(string $file): array
    {
        $path = self::$dataDir . DIRECTORY_SEPARATOR . $file;
        if (!file_exists($path)) {
            return [];
        }
        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            return [];
        }
        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function write(string $file, array $data): void
    {
        $path = self::$dataDir . DIRECTORY_SEPARATOR . $file;
        $fp = fopen($path, 'c+');
        if ($fp === false) {
            throw new RuntimeException("Cannot open file for writing: $path");
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException("Cannot acquire lock on: $path");
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }

    public static function findById(string $file, string $id): ?array
    {
        $records = self::read($file);
        foreach ($records as $record) {
            if (isset($record['id']) && $record['id'] === $id) {
                return $record;
            }
        }
        return null;
    }

    public static function upsert(string $file, array $item): void
    {
        $records = self::read($file);
        $found = false;
        foreach ($records as $i => $record) {
            if (isset($record['id']) && $record['id'] === $item['id']) {
                $records[$i] = $item;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $records[] = $item;
        }
        self::write($file, $records);
    }

    public static function delete(string $file, string $id): void
    {
        $records = self::read($file);
        $records = array_values(array_filter($records, fn($r) => ($r['id'] ?? null) !== $id));
        self::write($file, $records);
    }
}

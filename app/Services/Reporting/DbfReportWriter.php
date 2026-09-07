<?php

namespace App\Services\Reporting;

use RuntimeException;

/**
 * Minimal dBASE III writer for legacy Visual FoxPro interoperability.
 * Supports Character and Numeric fields required by operational exports.
 */
final class DbfReportWriter
{
    /**
     * @param array<int,array{name:string,type:string,length:int,decimals?:int}> $fields
     * @param iterable<int,array<string,mixed>> $rows
     */
    public function write(string $path, array $fields, iterable $rows, ?callable $progress = null, ?int $total = null): void
    {
        $this->validateFields($fields);

        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create DBF output directory.');
        }

        $handle = fopen($path, 'w+b');
        if ($handle === false) {
            throw new RuntimeException('Could not open DBF output file for writing.');
        }

        $recordLength = 1 + array_sum(array_map(fn (array $field): int => (int) $field['length'], $fields));
        $headerLength = 32 + (count($fields) * 32) + 1;

        try {
            fwrite($handle, str_repeat("\0", $headerLength));

            $count = 0;
            foreach ($rows as $row) {
                fwrite($handle, ' '); // Active record, not deleted.
                foreach ($fields as $field) {
                    fwrite($handle, $this->formatValue($row[$field['name']] ?? null, $field));
                }
                $count++;
                if ($progress !== null) {
                    $progress($count, max(1, (int) ($total ?? $count)));
                }
            }

            fwrite($handle, "\x1A");
            fflush($handle);

            fseek($handle, 0);
            $now = now();
            $header = chr(0x03)
                . chr(max(0, min(255, (int) $now->format('Y') - 1900)))
                . chr((int) $now->format('n'))
                . chr((int) $now->format('j'))
                . pack('V', $count)
                . pack('v', $headerLength)
                . pack('v', $recordLength)
                . str_repeat("\0", 20);
            fwrite($handle, $header);

            foreach ($fields as $field) {
                $name = substr((string) $field['name'], 0, 10);
                $descriptor = str_pad($name, 11, "\0", STR_PAD_RIGHT)
                    . strtoupper((string) $field['type'])
                    . str_repeat("\0", 4)
                    . chr((int) $field['length'])
                    . chr((int) ($field['decimals'] ?? 0))
                    . str_repeat("\0", 14);
                fwrite($handle, $descriptor);
            }
            fwrite($handle, "\x0D");
        } finally {
            fclose($handle);
        }
    }

    /** @param array<int,array{name:string,type:string,length:int,decimals?:int}> $fields */
    private function validateFields(array $fields): void
    {
        if ($fields === []) {
            throw new RuntimeException('DBF requires at least one field.');
        }

        $seen = [];
        foreach ($fields as $field) {
            $name = strtoupper(trim((string) ($field['name'] ?? '')));
            $type = strtoupper(trim((string) ($field['type'] ?? '')));
            $length = (int) ($field['length'] ?? 0);
            $decimals = (int) ($field['decimals'] ?? 0);

            if ($name === '' || strlen($name) > 10 || ! preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
                throw new RuntimeException('Invalid DBF field name: '.$name);
            }
            if (isset($seen[$name])) {
                throw new RuntimeException('Duplicate DBF field name: '.$name);
            }
            if (! in_array($type, ['C', 'N'], true)) {
                throw new RuntimeException('Unsupported DBF field type: '.$type);
            }
            if ($length < 1 || $length > 254) {
                throw new RuntimeException('Invalid DBF field length for '.$name.'.');
            }
            if ($type === 'N' && ($decimals < 0 || $decimals >= $length)) {
                throw new RuntimeException('Invalid DBF decimal count for '.$name.'.');
            }
            $seen[$name] = true;
        }
    }

    /** @param array{name:string,type:string,length:int,decimals?:int} $field */
    private function formatValue(mixed $value, array $field): string
    {
        $length = (int) $field['length'];
        $type = strtoupper((string) $field['type']);

        if ($type === 'N') {
            if ($value === null || $value === '') {
                return str_repeat(' ', $length);
            }
            $decimals = (int) ($field['decimals'] ?? 0);
            $formatted = $decimals > 0
                ? number_format((float) $value, $decimals, '.', '')
                : (string) (int) $value;
            if (strlen($formatted) > $length) {
                throw new RuntimeException('Numeric value exceeds DBF field width for '.$field['name'].'.');
            }
            return str_pad($formatted, $length, ' ', STR_PAD_LEFT);
        }

        $text = (string) ($value ?? '');
        // The agreed dataset contains ASCII operational values only. Replace any
        // unexpected control bytes so legacy readers receive a stable record width.
        $text = preg_replace('/[\x00-\x1F\x7F]/', ' ', $text) ?? '';
        if (strlen($text) > $length) {
            $text = substr($text, 0, $length);
        }
        return str_pad($text, $length, ' ', STR_PAD_RIGHT);
    }
}

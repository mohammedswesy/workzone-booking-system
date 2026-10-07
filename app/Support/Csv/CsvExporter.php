<?php

namespace App\Support\Csv;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    public const BOM = "\xEF\xBB\xBF";

    /**
     * Resolve AR/EN from query, cookie, or app locale and apply it for this request.
     */
    public static function applyLocale(?Request $request = null): string
    {
        $request ??= request();
        $candidates = [
            $request->query('locale'),
            $request->cookie('wz_locale'),
            app()->getLocale(),
        ];

        $locale = 'en';
        foreach ($candidates as $candidate) {
            $normalized = strtolower(substr((string) $candidate, 0, 2));
            if (in_array($normalized, ['ar', 'en'], true)) {
                $locale = $normalized;
                break;
            }
        }

        app()->setLocale($locale);

        return $locale;
    }

    /**
     * Neutralize CSV/formula injection for Excel.
     */
    public static function escapeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i');
        }

        $string = (string) $value;

        // Normalize newlines that can break CSV rows / injection chains.
        $string = str_replace(["\r\n", "\r", "\n"], ' ', $string);

        if ($string !== '' && preg_match('/^[=+\-@\t\r]/u', $string) === 1) {
            return "'".$string;
        }

        return $string;
    }

    /**
     * @param  list<string>  $headers
     * @param  callable(callable(array<int, mixed>): void): void  $writeRows
     *                                                                        receives a $write(array $row) callback and should stream rows through it
     */
    public static function download(string $filename, array $headers, callable $writeRows): StreamedResponse
    {
        $filename = self::safeFilename($filename);

        return response()->streamDownload(function () use ($headers, $writeRows) {
            $out = fopen('php://output', 'w');
            fwrite($out, self::BOM);

            fputcsv($out, array_map([self::class, 'escapeCell'], $headers));

            $write = static function (array $row) use ($out): void {
                fputcsv($out, array_map([self::class, 'escapeCell'], $row));
            };

            $writeRows($write);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public static function formatMoney(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    public static function formatDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $carbon = $value instanceof \DateTimeInterface
                ? \Illuminate\Support\Carbon::instance(\Illuminate\Support\Carbon::parse($value->format('c')))
                : \Illuminate\Support\Carbon::parse($value);

            return $carbon->timezone(config('app.display_timezone', 'Asia/Gaza'))->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public static function label(string $key, ?string $fallback = null): string
    {
        $translated = __('csv.'.$key);

        if ($translated === 'csv.'.$key) {
            return $fallback ?? $key;
        }

        return $translated;
    }

    public static function statusLabel(string $group, mixed $status): string
    {
        $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
        if ($value === '') {
            return '';
        }

        return self::label('statuses.'.$group.'.'.$value, $value);
    }

    private static function safeFilename(string $filename): string
    {
        $filename = basename($filename);
        if (! str_ends_with(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'export.csv';
    }
}

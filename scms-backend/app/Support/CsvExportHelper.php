<?php

namespace App\Support;

use App\Models\Concern;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportHelper
{
    /**
     * Expected CSV header columns for SCMS Concern Reports.
     */
    public const HEADERS = [
        'Concern ID',
        'Title',
        'Description',
        'Summary',
        'Category',
        'Subcategory',
        'Priority',
        'Status',
        'Workflow Type',
        'Assigned Department / Unit',
        'Student',
        'Sentiment',
        'Submitted At',
        'Last Updated At',
        'Resolved At',
        'Resolution Details',
    ];

    /**
     * Sanitize a string value to prevent CSV / spreadsheet formula injection.
     * Any value starting with =, +, -, @, tab, or CR is prefixed with a single quote.
     */
    public static function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = (string) $value;
        // Normalize line endings to LF so fputcsv encapsulates multiline cells cleanly
        $str = str_replace(["\r\n", "\r"], "\n", $str);

        if ($str === '') {
            return '';
        }

        $firstChar = $str[0];
        $trimmedFirst = ltrim($str, " \t\n")[0] ?? '';

        if (
            in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true) ||
            in_array($trimmedFirst, ['=', '+', '-', '@'], true)
        ) {
            return "'" . $str;
        }

        return $str;
    }

    /**
     * Format a timestamp consistently for CSV output (Y-m-d H:i:s).
     */
    public static function formatDate(mixed $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    /**
     * Format a human-readable category label for a concern.
     */
    public static function formatCategory(Concern $concern): string
    {
        $raw = trim((string) ($concern->issue_category ?: $concern->category ?: ''));
        if ($raw === '') {
            return 'Uncategorized';
        }

        return match ($raw) {
            'academic_units'          => 'Academic Concerns',
            'operational_departments' => 'Operational Departments',
            default                   => $raw,
        };
    }

    /**
     * Extract resolution details from resolution_summary or routing history when available.
     */
    public static function extractResolutionDetails(Concern $concern): string
    {
        if (!empty($concern->resolution_summary)) {
            return trim((string) $concern->resolution_summary);
        }

        if ($concern->relationLoaded('routingHistory') && $concern->routingHistory) {
            $resolvedEntry = $concern->routingHistory
                ->filter(function ($entry) use ($concern) {
                    return $entry->action === 'resolved'
                        || ($entry->action === 'status_changed' && $concern->status === 'resolved');
                })
                ->sortByDesc('created_at')
                ->first();

            if ($resolvedEntry && !empty($resolvedEntry->notes)) {
                return trim((string) $resolvedEntry->notes);
            }
        }

        if ($concern->status === 'resolved' && !empty($concern->resolved_at)) {
            return 'Resolved';
        }

        return '';
    }

    /**
     * Stream a collection of Concerns as a downloadable CSV response.
     */
    public static function streamConcernsCsv(Collection $concerns, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type'           => 'text/csv; charset=UTF-8',
            'Content-Disposition'    => 'attachment; filename="' . $filename . '"',
            'Cache-Control'          => 'private, no-cache, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $callback = function () use ($concerns) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::HEADERS);

            foreach ($concerns as $c) {
                $studentName = $c->is_anonymous
                    ? 'Anonymous'
                    : ($c->student?->name ?? 'N/A');

                $assignedUnit = $c->department?->name
                    ?? $c->academicUnit?->name
                    ?? 'N/A';

                $sentiment = !empty($c->sentiment_label)
                    ? (string) $c->sentiment_label
                    : '';

                $summary = !empty($c->ai_summary)
                    ? (string) $c->ai_summary
                    : \Illuminate\Support\Str::limit((string) $c->description, 200);

                fputcsv($handle, [
                    $c->concern_id,
                    self::sanitizeCell($c->title),
                    self::sanitizeCell($c->description),
                    self::sanitizeCell($summary),
                    self::sanitizeCell(self::formatCategory($c)),
                    self::sanitizeCell($c->issue_subcategory ?? ''),
                    self::sanitizeCell($c->priority ?? 'N/A'),
                    self::sanitizeCell($c->status ?? 'N/A'),
                    self::sanitizeCell($c->workflow_type ?? 'N/A'),
                    self::sanitizeCell($assignedUnit),
                    self::sanitizeCell($studentName),
                    self::sanitizeCell($sentiment),
                    self::formatDate($c->submitted_at),
                    self::formatDate($c->last_updated ?? $c->updated_at),
                    self::formatDate($c->resolved_at),
                    self::sanitizeCell(self::extractResolutionDetails($c)),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}

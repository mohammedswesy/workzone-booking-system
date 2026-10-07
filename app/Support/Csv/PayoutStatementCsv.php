<?php

namespace App\Support\Csv;

use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutStatementCsv
{
    public static function download(OwnerPayout $payout): StreamedResponse
    {
        CsvExporter::applyLocale();

        $payout->loadMissing('owner:id,name');

        $headers = [
            CsvExporter::label('payout_id'),
            CsvExporter::label('owner'),
            CsvExporter::label('entry_type'),
            CsvExporter::label('amount'),
            CsvExporter::label('currency'),
            CsvExporter::label('booking_id'),
            CsvExporter::label('venue'),
            CsvExporter::label('workspace'),
            CsvExporter::label('note'),
            CsvExporter::label('transfer_reference'),
            CsvExporter::label('created_at'),
        ];

        $filename = 'payout-'.$payout->id.'-statement.csv';

        return CsvExporter::download($filename, $headers, function (callable $write) use ($payout) {
            OwnerLedgerEntry::query()
                ->where('owner_id', $payout->owner_id)
                ->where(function ($q) use ($payout) {
                    $q->where('payout_id', $payout->id)
                        ->orWhereNotNull('booking_id');
                })
                ->with(['booking.workspace.venue:id,name', 'booking.workspace:id,name,venue_id'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->cursor()
                ->each(function (OwnerLedgerEntry $entry) use ($write, $payout) {
                    $write([
                        $payout->id,
                        $payout->owner?->name,
                        CsvExporter::statusLabel('ledger', $entry->type),
                        CsvExporter::formatMoney($entry->amount),
                        $entry->currency,
                        $entry->booking_id,
                        $entry->booking?->workspace?->venue?->name,
                        $entry->booking?->workspace?->name,
                        $entry->note,
                        $payout->transfer_reference,
                        CsvExporter::formatDateTime($entry->created_at),
                    ]);
                });
        });
    }
}

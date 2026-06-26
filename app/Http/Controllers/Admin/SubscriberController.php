<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function index()
    {
        $subscribers = Subscriber::orderByDesc('created_at')->paginate(50);
        $total       = Subscriber::count();

        return view('admin.subscribers.index', compact('subscribers', 'total'));
    }

    /** Stream the full subscriber list as CSV (memory-safe via a cursor). */
    public function export(): StreamedResponse
    {
        $filename = 'subscribers-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'source', 'subscribed_at']);

            Subscriber::orderByDesc('created_at')->cursor()->each(function (Subscriber $s) use ($out) {
                fputcsv($out, [
                    $this->csvSafe($s->email),
                    $this->csvSafe($s->source),
                    optional($s->created_at)->toDateTimeString(),
                ]);
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Neutralise CSV/formula injection: a leading =, +, -, @, tab or CR makes a
     * spreadsheet treat the cell as a formula. Prefix those with an apostrophe.
     */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}

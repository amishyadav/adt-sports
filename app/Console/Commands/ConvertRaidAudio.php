<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Re-encodes a WAV into the 16-bit PCM form browsers reliably decode.
 *
 * Chrome (and therefore OBS's browser source) does not decode 24-bit PCM WAV —
 * the <audio> element fails with "source not supported" and plays nothing. Audio
 * exported from a DAW is very often 24-bit or 32-bit float, so this converts
 * whatever was dropped into public/audio down to 16-bit, which every browser
 * handles.
 *
 * Pure PHP on purpose: ffmpeg is not installed on the venue machine.
 */
class ConvertRaidAudio extends Command
{
    protected $signature = 'raid:audio-convert
                            {source : WAV inside public/audio, e.g. "My Countdown.wav"}
                            {--out=raid-countdown.wav : Output filename written next to the source}';

    protected $description = 'Convert a raid countdown WAV to browser-safe 16-bit PCM';

    public function handle(): int
    {
        $dir = public_path('audio');
        $source = $dir . DIRECTORY_SEPARATOR . $this->argument('source');
        $target = $dir . DIRECTORY_SEPARATOR . $this->option('out');

        if (! is_file($source)) {
            $this->error('Not found: ' . $source);

            return self::FAILURE;
        }

        $wav = $this->readWav($source);
        if ($wav === null) {
            return self::FAILURE;
        }

        [$fmt, $data] = $wav;

        $this->line(sprintf(
            '  Source: %d Hz, %d-bit, %d ch, %.2f s',
            $fmt['rate'],
            $fmt['bits'],
            $fmt['channels'],
            strlen($data) / ($fmt['rate'] * $fmt['channels'] * ($fmt['bits'] / 8))
        ));

        $pcm = $this->toSixteenBit($data, $fmt);
        if ($pcm === null) {
            return self::FAILURE;
        }

        file_put_contents($target, $this->wavHeader($fmt['channels'], $fmt['rate'], strlen($pcm)) . $pcm);

        $this->info('  Wrote ' . $this->option('out') . ' — ' . number_format(strlen($pcm) + 44) . ' bytes, 16-bit PCM');
        $this->line('  Reference it from the live overlay as /audio/' . rawurlencode($this->option('out')));

        return self::SUCCESS;
    }

    /** @return array{0: array, 1: string}|null  [fmt chunk fields, raw sample data] */
    private function readWav(string $path): ?array
    {
        $handle = fopen($path, 'rb');
        $riff = fread($handle, 12);

        if (substr($riff, 0, 4) !== 'RIFF' || substr($riff, 8, 4) !== 'WAVE') {
            fclose($handle);
            $this->error('Not a RIFF/WAVE file.');

            return null;
        }

        $fmt = null;
        $data = '';

        while (! feof($handle)) {
            $header = fread($handle, 8);
            if (strlen($header) < 8) {
                break;
            }

            $id = substr($header, 0, 4);
            $size = unpack('V', substr($header, 4, 4))[1];

            if ($id === 'fmt ') {
                $raw = fread($handle, $size);
                $fmt = unpack('vformat/vchannels/Vrate/VbyteRate/vblockAlign/vbits', substr($raw, 0, 16));
            } elseif ($id === 'data') {
                $data = fread($handle, $size);
            } else {
                fseek($handle, $size + ($size % 2), SEEK_CUR);
            }
        }

        fclose($handle);

        if (! $fmt || $data === '') {
            $this->error('Missing fmt or data chunk.');

            return null;
        }

        return [$fmt, $data];
    }

    /**
     * Narrow the samples to signed 16-bit little-endian.
     *
     * 24-bit and 32-bit integer are truncated to their top two bytes, which is
     * inaudible on a countdown bed. 32-bit float is scaled and clamped.
     */
    private function toSixteenBit(string $data, array $fmt): ?string
    {
        $isFloat = $fmt['format'] === 3;
        $bits = $fmt['bits'];

        if (! $isFloat && $bits === 16) {
            $this->line('  Already 16-bit — copying samples through.');

            return $data;
        }

        $length = strlen($data);
        $out = [];

        if ($isFloat && $bits === 32) {
            for ($i = 0; $i + 3 < $length; $i += 4) {
                $sample = unpack('g', substr($data, $i, 4))[1];
                $scaled = (int) round(max(-1.0, min(1.0, $sample)) * 32767);
                $out[] = pack('v', $scaled & 0xFFFF);
            }
        } elseif ($bits === 24) {
            // Little-endian: bytes 1 and 2 are the most significant 16 bits.
            for ($i = 0; $i + 2 < $length; $i += 3) {
                $out[] = $data[$i + 1] . $data[$i + 2];
            }
        } elseif ($bits === 32) {
            for ($i = 0; $i + 3 < $length; $i += 4) {
                $out[] = $data[$i + 2] . $data[$i + 3];
            }
        } else {
            $this->error("Unsupported source format: {$bits}-bit" . ($isFloat ? ' float' : ''));

            return null;
        }

        return implode('', $out);
    }

    /** Canonical 44-byte PCM WAV header. */
    private function wavHeader(int $channels, int $rate, int $dataBytes): string
    {
        $byteRate = $rate * $channels * 2;

        return 'RIFF'
            . pack('V', 36 + $dataBytes)
            . 'WAVE'
            . 'fmt '
            . pack('V', 16)
            . pack('v', 1)            // PCM
            . pack('v', $channels)
            . pack('V', $rate)
            . pack('V', $byteRate)
            . pack('v', $channels * 2)
            . pack('v', 16)
            . 'data'
            . pack('V', $dataBytes);
    }
}

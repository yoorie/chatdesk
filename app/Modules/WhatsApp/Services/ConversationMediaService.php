<?php

namespace App\Modules\WhatsApp\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationMediaService
{
    public function validateMediaAttachment(\Illuminate\Http\UploadedFile $file, string $type): void
    {
        $clientMime = Str::lower((string) $file->getClientMimeType());
        $detectedMime = Str::lower((string) ($file->getMimeType() ?: ''));
        $extension = Str::lower((string) ($file->getClientOriginalExtension() ?: $file->extension()));
        $mimeValues = array_filter([$clientMime, $detectedMime]);

        $allowed = [
            'image' => [
                'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                'max_kb' => 5120,
                'message' => 'The attachment field must be an image of type: jpg, jpeg, png, gif, webp.',
            ],
            'video' => [
                'extensions' => ['mp4', 'mov', '3gp', '3gpp'],
                'mimes' => ['video/mp4', 'video/quicktime', 'video/3gpp'],
                'max_kb' => 16384,
                'message' => 'The attachment field must be a video of type: mp4, mov, 3gp.',
            ],
            'document' => [
                'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip'],
                'mimes' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'text/plain',
                    'text/csv',
                    'application/csv',
                    'application/zip',
                    'application/x-zip-compressed',
                ],
                'max_kb' => 102400,
                'message' => 'The attachment field must be a document of type: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv, zip.',
            ],
            'audio' => [
                'extensions' => ['aac', 'm4a', 'mp4', 'mp3', 'amr', 'ogg', 'oga', 'wav', 'wave'],
                'mimes' => [
                    'audio/aac',
                    'audio/mp4',
                    'audio/x-m4a',
                    'audio/m4a',
                    'audio/mpeg',
                    'audio/mp3',
                    'audio/amr',
                    'audio/ogg',
                    'application/ogg',
                    'audio/wav',
                    'audio/x-wav',
                    'audio/wave',
                    'audio/vnd.wave',
                    'application/octet-stream',
                ],
                'max_kb' => 16384,
                'message' => 'The attachment field must be an audio file of type: aac, m4a, mp3, amr, ogg, wav.',
            ],
        ];

        $rules = $allowed[$type] ?? null;
        if (! $rules) {
            return;
        }

        $extensionAllowed = in_array($extension, $rules['extensions'], true);
        $mimeAllowed = ! empty(array_intersect($mimeValues, $rules['mimes']));

        if (! $extensionAllowed && ! $mimeAllowed) {
            throw ValidationException::withMessages([
                'attachment' => $rules['message'],
            ]);
        }

        $maxKb = (int) ($rules['max_kb'] ?? 10240);
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'attachment' => "The attachment field must not be greater than {$maxKb} KB.",
            ]);
        }
    }

    public function storagePathFromPublicUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $storagePrefix = '/storage/';
        $position = strpos($path, $storagePrefix);
        if ($position === false) {
            return null;
        }

        return ltrim(substr($path, $position + strlen($storagePrefix)), '/');
    }

    public function metaMediaMimeType(string $type, ?string $mimeType = null, ?string $filename = null, ?string $path = null): ?string
    {
        $mimeType = strtolower((string) $mimeType);
        $extension = strtolower(pathinfo($filename ?: $path ?: '', PATHINFO_EXTENSION));

        if ($type === 'audio') {
            return match (true) {
                in_array($mimeType, ['audio/mp4', 'audio/x-m4a', 'audio/m4a'], true),
                in_array($extension, ['m4a', 'mp4'], true) => 'audio/mp4',
                in_array($mimeType, ['audio/mpeg', 'audio/mp3'], true),
                in_array($extension, ['mp3'], true) => 'audio/mpeg',
                $mimeType === 'audio/aac' || $extension === 'aac' => 'audio/aac',
                $mimeType === 'audio/amr' || $extension === 'amr' => 'audio/amr',
                in_array($mimeType, ['audio/ogg', 'application/ogg'], true),
                in_array($extension, ['ogg', 'oga', 'opus'], true) => 'audio/ogg',
                in_array($mimeType, ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'], true),
                in_array($extension, ['wav', 'wave'], true) => 'audio/wav',
                default => $mimeType ?: null,
            };
        }

        if ($type === 'image' && $extension === 'jpg') {
            return 'image/jpeg';
        }

        if ($type === 'video' && in_array($extension, ['3gp', '3gpp'], true)) {
            return 'video/3gpp';
        }

        return $mimeType ?: null;
    }
}

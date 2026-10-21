<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppList extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_lists';

    protected $fillable = [
        'account_id',
        'whatsapp_connection_id',
        'name',
        'button_text',
        'description',
        'footer_text',
        'sections',
        'is_active',
    ];

    protected $casts = [
        'sections' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the WhatsApp connection.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConnection::class, 'whatsapp_connection_id');
    }

    /**
     * Validate list structure according to Meta's requirements.
     */
    public function validateStructure(): array
    {
        $errors = [];

        // Button text validation (max 20 characters)
        if (mb_strlen($this->button_text) > 20) {
            $errors[] = 'Button text must be 20 characters or less.';
        }

        // Description validation (max 1024 characters)
        if ($this->description && mb_strlen($this->description) > 1024) {
            $errors[] = 'Description must be 1024 characters or less.';
        }

        // Footer validation (max 60 characters)
        if ($this->footer_text && mb_strlen($this->footer_text) > 60) {
            $errors[] = 'Footer text must be 60 characters or less.';
        }

        // Sections validation
        if (empty($this->sections) || ! is_array($this->sections)) {
            $errors[] = 'List must have at least one section.';
        } else {
            if (count($this->sections) > 10) {
                $errors[] = 'List can have maximum 10 sections.';
            }

            $totalRows = 0;
            foreach ($this->sections as $index => $section) {
                // Section title validation (max 24 characters)
                if (empty($section['title']) || mb_strlen($section['title']) > 24) {
                    $errors[] = 'Section '.($index + 1).': Title is required and must be 24 characters or less.';
                }

                // Rows validation
                if (empty($section['rows']) || ! is_array($section['rows'])) {
                    $errors[] = 'Section '.($index + 1).': Must have at least one row.';
                } else {
                    if (count($section['rows']) > 10) {
                        $errors[] = 'Section '.($index + 1).': Can have maximum 10 rows.';
                    }

                    foreach ($section['rows'] as $rowIndex => $row) {
                        // Row ID validation (max 200 characters, unique)
                        if (empty($row['id']) || mb_strlen($row['id']) > 200) {
                            $errors[] = 'Section '.($index + 1).', Row '.($rowIndex + 1).': ID is required and must be 200 characters or less.';
                        }

                        // Row title validation (max 24 characters)
                        if (empty($row['title']) || mb_strlen($row['title']) > 24) {
                            $errors[] = 'Section '.($index + 1).', Row '.($rowIndex + 1).': Title is required and must be 24 characters or less.';
                        }

                        // Row description validation (max 72 characters, optional)
                        if (isset($row['description']) && mb_strlen($row['description']) > 72) {
                            $errors[] = 'Section '.($index + 1).', Row '.($rowIndex + 1).': Description must be 72 characters or less.';
                        }
                    }

                    $totalRows += count($section['rows']);
                }
            }

            // Total rows validation (max 10 rows across all sections)
            if ($totalRows > 10) {
                $errors[] = 'Total rows across all sections cannot exceed 10.';
            }
        }

        return $errors;
    }

    /**
     * Convert to Meta API format.
     */
    public function toMetaFormat(): array
    {
        $sections = [];
        foreach ($this->sections as $section) {
            $rows = [];
            foreach ($section['rows'] as $row) {
                $rowData = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                ];
                if (! empty($row['description'])) {
                    $rowData['description'] = $row['description'];
                }
                $rows[] = $rowData;
            }

            $sections[] = [
                'title' => $section['title'],
                'rows' => $rows,
            ];
        }

        $interactive = [
            'type' => 'list',
            'header' => $this->description ? [
                'type' => 'text',
                'text' => $this->description,
            ] : null,
            'body' => [
                'text' => $this->description ?: $this->name,
            ],
            'footer' => $this->footer_text ? [
                'text' => $this->footer_text,
            ] : null,
            'action' => [
                'button' => $this->button_text,
                'sections' => $sections,
            ],
        ];

        // Remove null values
        if ($interactive['header'] === null) {
            unset($interactive['header']);
        }
        if ($interactive['footer'] === null) {
            unset($interactive['footer']);
        }

        return $interactive;
    }
}

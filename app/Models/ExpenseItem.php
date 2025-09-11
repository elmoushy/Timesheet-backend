<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'expense_id',
        'date',
        'type',
        'currency',
        'amount',
        'currency_rate',
        'description',
        'attachment_blob',
        'attachment_filename',
        'attachment_mime_type',
        'attachment_size',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'currency_rate' => 'decimal:4',
    ];

    /**
     * Get the expense that owns the expense item.
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Get the amount in EGP.
     */
    public function getAmountInEgpAttribute(): float
    {
        if ($this->currency === 'EGP') {
            return (float) $this->amount;
        }

        return (float) ($this->amount * $this->currency_rate);
    }

    /**
     * Store file as blob.
     */
    public function storeFile($file): void
    {
        $this->attachment_blob = file_get_contents($file->getRealPath());
        $this->attachment_filename = $file->getClientOriginalName();
        $this->attachment_mime_type = $file->getMimeType();
        $this->attachment_size = $file->getSize();
    }

    /**
     * Get file data for download.
     */
    public function getFileData(): ?array
    {
        if (!$this->attachment_blob) {
            return null;
        }

        return [
            'content' => $this->attachment_blob,
            'filename' => $this->attachment_filename,
            'mime_type' => $this->attachment_mime_type,
            'size' => $this->attachment_size,
        ];
    }

    /**
     * Check if item has attachment.
     */
    public function hasAttachment(): bool
    {
        return !empty($this->attachment_blob);
    }

    /**
     * Get attachment URL for API responses.
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->hasAttachment()) {
            return null;
        }

        return url("/api/files/expense-items/{$this->id}/attachment");
    }

    /**
     * Get attachment as base64 data URI.
     */
    public function getAttachmentDataUriAttribute(): ?string
    {
        if (!$this->hasAttachment()) {
            return null;
        }

        try {
            $mimeType = $this->attachment_mime_type ?: 'application/octet-stream';
            $base64Data = base64_encode($this->attachment_blob);

            return "data:{$mimeType};base64,{$base64Data}";
        } catch (\Exception $e) {
            \Log::error('Error encoding attachment to base64: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get attachment as base64 string only.
     */
    public function getAttachmentBase64Attribute(): ?string
    {
        if (!$this->hasAttachment()) {
            return null;
        }

        try {
            return base64_encode($this->attachment_blob);
        } catch (\Exception $e) {
            \Log::error('Error encoding attachment to base64: ' . $e->getMessage());
            return null;
        }
    }
}

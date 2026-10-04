<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WorkspaceImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'path',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected $appends = ['url'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://') || str_starts_with($this->path, '/storage/')) {
            return $this->path;
        }

        return Storage::disk('public')->url($this->path);
    }

    public function deleteFile(): void
    {
        if (str_starts_with($this->path, 'workspaces/')) {
            Storage::disk('public')->delete($this->path);
        } elseif (str_starts_with($this->path, '/storage/workspaces/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $this->path));
        }
    }
}

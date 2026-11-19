<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\PersonnelFolderSlugCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeDocumentBatch extends Model
{
    protected $fillable = [
        'security_company_id',
        'employee_id',
        'folder',
        'original_name',
        'disk_path',
        'mime',
        'page_count',
    ];

    protected function casts(): array
    {
        return [
            'folder' => PersonnelFolderSlugCast::class,
            'page_count' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

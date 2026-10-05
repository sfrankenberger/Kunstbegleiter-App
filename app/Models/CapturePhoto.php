<?php

namespace App\Models;

use App\Enums\PhotoType;
use Database\Factories\CapturePhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

#[Fillable(['capture_id', 'path', 'type', 'width', 'height', 'ocr_text', 'sort_order'])]
/**
 * Ein Foto einer Aufnahme auf der privaten Platte (storage/app/private), Auslieferung nur ueber signierte Routen.
 */
class CapturePhoto extends Model
{
    /** @use HasFactory<CapturePhotoFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => PhotoType::class,
        ];
    }

    /** @return BelongsTo<Capture, $this> */
    public function capture(): BelongsTo
    {
        return $this->belongsTo(Capture::class);
    }

    /**
     * Signierte Adresse fuer das Bild (60 Minuten), Auslieferung ueber PhotoController.
     */
    public function url(): string
    {
        return URL::temporarySignedRoute('fotos.show', now()->addHour(), ['photo' => $this->getKey()]);
    }
}

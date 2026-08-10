<?php

namespace App\Models\Concerns;

use LogicException;

trait HasDocumentNumber
{
    public static function bootHasDocumentNumber(): void
    {
        static::created(function ($model) {
            $model->assignDocumentNumber();
        });

        static::updating(function ($model) {
            $column = $model->documentNumberColumn();

            if ($model->getOriginal($column) && $model->isDirty($column)) {
                throw new LogicException('Document numbers are immutable.');
            }
        });
    }

    public function assignDocumentNumber(): string
    {
        $column = $this->documentNumberColumn();
        $existingNumber = $this->getAttribute($column);

        if ($existingNumber) {
            return $existingNumber;
        }

        if (!$this->exists || !$this->getKey()) {
            throw new LogicException('A document number requires a persisted model.');
        }

        $year = ($this->created_at ?? now())->format('Y');
        $number = sprintf('%s-%s-%06d', $this->documentNumberPrefix(), $year, $this->getKey());

        $this->setAttribute($column, $number);
        $this->saveQuietly();

        return $number;
    }

    abstract protected function documentNumberColumn(): string;

    abstract protected function documentNumberPrefix(): string;
}

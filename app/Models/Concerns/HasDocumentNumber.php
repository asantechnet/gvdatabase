<?php

namespace App\Models\Concerns;

use LogicException;

trait HasDocumentNumber
{
    public static function bootHasDocumentNumber(): void
    {
        static::created(function ($model) {
            if ($model->documentNumberIsIssued()) {
                $model->assignDocumentNumber();
            }
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

        $year = ($this->created_at ?? now())->format($this->documentNumberYearFormat());
        $number = sprintf($this->documentNumberFormat(), $this->documentNumberPrefix(), $year, $this->getKey());

        $this->setAttribute($column, $number);
        $this->saveQuietly();

        return $number;
    }

    abstract protected function documentNumberColumn(): string;

    /**
     * A row can exist before its number is issued; a draft receipt is the
     * example. Such a model gets its number when it is approved.
     */
    protected function documentNumberIsIssued(): bool
    {
        return true;
    }

    abstract protected function documentNumberPrefix(): string;

    protected function documentNumberFormat(): string
    {
        return '%s-%s-%06d';
    }

    protected function documentNumberYearFormat(): string
    {
        return 'Y';
    }
}

<?php

namespace michaeljmeadows\Traits;

use Illuminate\Support\Facades\DB;

trait HasHistories
{
    public function getIgnoredFields(): array
    {
        return $this->ignoredFields ?? [];
    }

    public function getHistoryTableName(): string
    {
        return $this->historiesTable ?? str($this->getTable())
            ->singular()
            ->append('_histories')
            ->toString();
    }

    public function getHistoryModelIdReference(): string
    {
        return $this->historiesModelIdReference ?? str($this->getTable())
            ->singular()
            ->append('_')
            ->append($this->getKeyName())
            ->toString();
    }

    public function saveHistory(?string $connection = null): void
    {
        $attributes = $this->getOriginal();
        $attributesToSave = array_diff(array_keys($attributes), $this->getIgnoredFields());

        if ($this->isDirty($attributesToSave)) {
            $interestingAttributes = array_intersect_key($attributes, array_flip($attributesToSave));

            $interestingAttributes[$this->getHistoryModelIdReference()] = $interestingAttributes[$this->getKeyName()];
            unset($interestingAttributes[$this->getKeyName()]);

            DB::connection($this->connection ?? $connection)->table($this->getHistoryTableName())->insert($interestingAttributes);
        }
    }
}

<?php

namespace michaeljmeadows\Traits;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

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

    public function restoreBeforeDate(DateTimeInterface|string $date, ?string $connection = null): bool
    {
        $history = DB::connection($this->connection ?? $connection)
            ->table($this->getHistoryTableName())
            ->where($this->getHistoryModelIdReference(), $this->id)
            ->where(function (Builder $query) use ($date) {
                $query->whereDate('updated_at', '<', $date)
                    ->orWhere(function (Builder $query) use ($date) {
                        $query->whereNull('updated_at')
                            ->whereDate('created_at', '<', $date);
                    });
            })
            ->orderByDesc('id')
            ->first();
        
        if (! $history) {
            return false;
        }

        $this->restoreFromHistory($history);

        return true;
    }

    public function restoreFromHistory(Object $object): void
    {
        foreach ($object as $attribute => $value) {
            if ($attribute == 'id' || $attribute == $this->getHistoryModelIdReference()) {
                continue;
            }

            $this->{$attribute} = $value;
        }        

        $this->save();
    }

    public function restorePrevious(?string $connection = null): bool
    {
        return $this->restorePreviousIteration(0, $connection);
    }

    public function restorePreviousIteration(int $index = 0, ?string $connection = null): bool
    {
        $history = DB::connection($this->connection ?? $connection)
            ->table($this->getHistoryTableName())
            ->where($this->getHistoryModelIdReference(), $this->id)
            ->orderByDesc('id')
            ->skip($index)
            ->first();

        if (! $history) {
            return false;
        }

        $this->restoreFromHistory($history);

        return true;
    }
}
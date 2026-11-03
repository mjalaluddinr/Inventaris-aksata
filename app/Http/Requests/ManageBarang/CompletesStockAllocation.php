<?php

namespace App\Http\Requests\ManageBarang;

trait CompletesStockAllocation
{
    protected function completeStockLocationAllocation(): void
    {
        $stockTotal = $this->integerInput($this->input('stok_total'));
        $stockAvailable = $this->integerInput($this->input('stok_tersedia'));
        $locations = $this->input('locations');

        if (
            $stockTotal === null
            || $stockAvailable === null
            || $stockAvailable > $stockTotal
            || ! is_array($locations)
            || $locations === []
        ) {
            return;
        }

        foreach ($locations as $location) {
            if (! is_array($location)) {
                return;
            }

            if (
                $this->integerInput($location['stok_total'] ?? null, allowEmpty: true) === null
                || $this->integerInput($location['stok_tersedia'] ?? null, allowEmpty: true) === null
            ) {
                return;
            }
        }

        if (count($locations) === 1) {
            $location = reset($locations);
            $location['stok_total'] = $stockTotal;
            $location['stok_tersedia'] = $stockAvailable;
            $locations = [$location];
            $this->merge(['locations' => $locations]);

            return;
        }

        $stockTotalAllocated = 0;
        $stockAvailableAllocated = 0;
        $emptyLocationIndexes = [];

        foreach ($locations as $index => $location) {
            $locationTotal = $this->integerInput($location['stok_total'] ?? null, allowEmpty: true) ?? 0;
            $locationAvailable = $this->integerInput($location['stok_tersedia'] ?? null, allowEmpty: true) ?? 0;
            $stockTotalAllocated += $locationTotal;
            $stockAvailableAllocated += $locationAvailable;

            if ($locationTotal === 0 && $locationAvailable === 0) {
                $emptyLocationIndexes[] = $index;
            }
        }

        $stockTotalRemaining = $stockTotal - $stockTotalAllocated;
        $stockAvailableRemaining = $stockAvailable - $stockAvailableAllocated;

        if (
            $emptyLocationIndexes === []
            || $stockTotalRemaining < 0
            || $stockAvailableRemaining < 0
            || $stockAvailableRemaining > $stockTotalRemaining
        ) {
            return;
        }

        foreach ($emptyLocationIndexes as $position => $index) {
            $remainingLocations = count($emptyLocationIndexes) - $position;
            $locationTotal = intdiv($stockTotalRemaining, $remainingLocations);
            if ($stockTotalRemaining % $remainingLocations > 0) {
                $locationTotal++;
            }

            $locationAvailable = intdiv($stockAvailableRemaining, $remainingLocations);
            if ($stockAvailableRemaining % $remainingLocations > 0) {
                $locationAvailable++;
            }

            $locationAvailable = min($locationTotal, $locationAvailable);
            $locations[$index]['stok_total'] = $locationTotal;
            $locations[$index]['stok_tersedia'] = $locationAvailable;
            $stockTotalRemaining -= $locationTotal;
            $stockAvailableRemaining -= $locationAvailable;
        }

        if ($stockTotalRemaining === 0 && $stockAvailableRemaining === 0) {
            $this->merge(['locations' => $locations]);
        }
    }

    private function integerInput(mixed $value, bool $allowEmpty = false): ?int
    {
        if ($allowEmpty && ($value === null || $value === '')) {
            return 0;
        }

        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^\d+$/D', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}

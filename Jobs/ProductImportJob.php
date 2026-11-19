<?php

namespace Amplify\System\Utility\Jobs;

use Amplify\System\Backend\Enums\ProductAvailabilityEnum;
use Amplify\System\Jobs\ProductServiceJob;
use Amplify\System\Utility\Listeners\ImportedProductFlags;
use ErrorException;

class ProductImportJob extends ProductServiceJob
{
    private const DISPLAY_FIELDS = [
        'availability_display' => 'availability',
        'price_display' => 'price',
    ];

    protected function handleColumnMapping(bool $failed = false): void
    {
        // The failure handler must still be able to collect the original CSV row.
        if (! $failed) {
            $fields = [];
            foreach ($this->column_mapping as $item) {
                if ($item->map_to !== 'Field') {
                    continue;
                }
                $field = $item->field_or_attribute_name;
                if (isset(self::DISPLAY_FIELDS[$field]) && isset($fields[$field])) {
                    throw new ErrorException("Duplicate import mapping for {$field}.");
                }
                $fields[$field] = true;
            }
            if (isset($fields['flags']) && array_intersect_key($fields, self::DISPLAY_FIELDS)) {
                throw new ErrorException('Map either flags or the display fields, not both.');
            }
        }

        parent::handleColumnMapping($failed);
    }

    protected function mapToField($item, $value): void
    {
        $field = $item->field_or_attribute_name;
        $flag = self::DISPLAY_FIELDS[$field] ?? null;

        if ($flag === null) {
            parent::mapToField($item, $value);

            return;
        }

        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return;
        }

        $allowed = $flag === 'availability'
            ? array_column(ProductAvailabilityEnum::cases(), 'value')
            : ['D', 'Y'];
        if (! in_array($value, $allowed, true)) {
            throw new ErrorException("Invalid {$field}: {$value}. Allowed values: ".implode(', ', $allowed).'.');
        }

        $flags = $this->product->flags ?? [];
        if (! is_array($flags)) {
            throw new ErrorException('Product flags must be a JSON object.');
        }
        $flags[$flag] = $value;
        $this->product->flags = $flags;
        ImportedProductFlags::track($this->product);
    }

    protected function saveDataToDatabase($aCsv): void
    {
        try {
            parent::saveDataToDatabase($aCsv);
        } finally {
            ImportedProductFlags::forget($this->product);
        }
    }
}

<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Abstracts\BackpackCustomCrudController;
use Amplify\System\Backend\Models\Manufacturer;
use Amplify\System\Backend\Models\Product;
use Amplify\System\Utility\Models\Export;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class ExportCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ExportCrudController extends BackpackCustomCrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(Export::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/export');
        CRUD::setEntityNameStrings('export', 'exports');
        CRUD::denyAccess(['create', 'delete', 'show', 'update']);
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     *
     * @return void
     */
    protected function setupListOperation()
    {
        $this->crud->setListView('backend::pages.exports.product');
        $this->data['productExportConditions'] = $this->productExportConditions();
        $this->data['productExportColumns'] = $this->productExportColumns();
        $this->data['manufacturerExportColumns'] = $this->manufacturerExportColumns();
        $this->data['selectedProductConditions'] = old('product_conditions', [
            'brand_condition' => request('brand_condition', 'any'),
            'manufacturer_condition' => request('manufacturer_condition', 'any'),
            'status_condition' => request('status_condition', 'any'),
            'has_sku_condition' => request('has_sku_condition', 'any'),
        ]);
        $this->data['defaultSelectedColumns'] = array_keys(array_filter(
            $this->productExportColumns(),
            fn ($column) => $column['selected_by_default'] ?? false
        ));
        $this->data['defaultManufacturerSelectedColumns'] = array_keys(array_filter(
            $this->manufacturerExportColumns(),
            fn ($column) => $column['selected_by_default'] ?? false
        ));
        $this->data['productCount'] = Product::count();
        $this->data['manufacturerCount'] = Manufacturer::count();
    }

    public function downloadProducts(Request $request): StreamedResponse
    {
        $columns = $this->productExportColumns();
        $selectedColumns = array_values(array_intersect(
            (array) $request->input('columns', []),
            array_keys($columns)
        ));

        if (empty($selectedColumns)) {
            $selectedColumns = array_keys(array_filter(
                $columns,
                fn ($column) => $column['selected_by_default'] ?? false
            ));
        }

        app()->setLocale($request->input('locale', app()->getLocale()));

        $fileName = 'product-export-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($columns, $selectedColumns, $request) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, array_map(
                fn (string $columnKey) => $columns[$columnKey]['label'],
                $selectedColumns
            ));

            $query = Product::query()
                ->with([
                    'manufacturerRelation',
                    'brand',
                    'categories',
                    'productClassification',
                ]);

            $this->applyProductExportConditions($query, $request);

            $query->orderBy('id')
                ->chunk(250, function ($products) use ($output, $columns, $selectedColumns) {
                    foreach ($products as $product) {
                        $row = [];
                        foreach ($selectedColumns as $columnKey) {
                            $row[] = $this->resolveProductExportValue($product, $columnKey);
                        }

                        fputcsv($output, $row);
                    }
                });

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadManufacturers(Request $request): StreamedResponse
    {
        $columns = $this->manufacturerExportColumns();
        $selectedColumns = array_values(array_intersect(
            (array) $request->input('columns', []),
            array_keys($columns)
        ));

        if (empty($selectedColumns)) {
            $selectedColumns = array_keys(array_filter(
                $columns,
                fn ($column) => $column['selected_by_default'] ?? false
            ));
        }

        $fileName = 'manufacturer-export-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($columns, $selectedColumns) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, array_map(
                fn (string $columnKey) => $columns[$columnKey]['label'],
                $selectedColumns
            ));

            Manufacturer::query()
                ->orderBy('id')
                ->chunk(250, function ($manufacturers) use ($output, $selectedColumns) {
                    foreach ($manufacturers as $manufacturer) {
                        $row = [];
                        foreach ($selectedColumns as $columnKey) {
                            $row[] = $this->resolveManufacturerExportValue($manufacturer, $columnKey);
                        }

                        fputcsv($output, $row);
                    }
                });

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, array{label: string, selected_by_default?: bool}>
     */
    protected function productExportConditions(): array
    {
        return [
            'brand_condition' => [
                'label' => 'Brand',
                'options' => [
                    'any' => 'Any',
                    'empty' => 'Empty',
                    'not_empty' => 'Not empty',
                ],
            ],
            'manufacturer_condition' => [
                'label' => 'Manufacturer',
                'options' => [
                    'any' => 'Any',
                    'empty' => 'Empty',
                    'not_empty' => 'Not empty',
                ],
            ],
            'status_condition' => [
                'label' => 'Status',
                'options' => [
                    'any' => 'Any',
                    'published' => 'Published',
                    'draft' => 'Draft',
                    'archived' => 'Archived',
                ],
            ],
            'has_sku_condition' => [
                'label' => 'Has SKU',
                'options' => [
                    'any' => 'Any',
                    'yes' => 'Yes',
                    'no' => 'No',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, selected_by_default?: bool}>
     */
    protected function productExportColumns(): array
    {
        return [
            'id' => [
                'label' => 'ID',
                'selected_by_default' => true,
            ],
            'product_code' => [
                'label' => 'Product Code',
                'selected_by_default' => true,
            ],
            'product_name' => [
                'label' => 'Product Name',
                'selected_by_default' => true,
            ],
            'local_product_name' => [
                'label' => 'Localized Product Name',
            ],
            'model_name' => [
                'label' => 'Model Name',
            ],
            'local_model_name' => [
                'label' => 'Localized Model Name',
            ],
            'short_description' => [
                'label' => 'Short Description',
            ],
            'local_short_description' => [
                'label' => 'Localized Short Description',
            ],
            'description' => [
                'label' => 'Description',
            ],
            'local_description' => [
                'label' => 'Localized Description',
            ],
            'product_type' => [
                'label' => 'Product Type',
            ],
            'status' => [
                'label' => 'Status',
                'selected_by_default' => true,
            ],
            'selling_price' => [
                'label' => 'Selling Price',
                'selected_by_default' => true,
            ],
            'msrp' => [
                'label' => 'MSRP',
            ],
            'manufacturer' => [
                'label' => 'Manufacturer',
                'selected_by_default' => true,
            ],
            'brand' => [
                'label' => 'Brand',
            ],
            'categories' => [
                'label' => 'Categories',
                'selected_by_default' => true,
            ],
            'product_classification' => [
                'label' => 'Product Classification',
            ],
            'created_at' => [
                'label' => 'Created At',
            ],
            'updated_at' => [
                'label' => 'Updated At',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, selected_by_default?: bool}>
     */
    protected function manufacturerExportColumns(): array
    {
        return [
            'id' => [
                'label' => 'ID',
                'selected_by_default' => true,
            ],
            'code' => [
                'label' => 'Code',
                'selected_by_default' => true,
            ],
            'name' => [
                'label' => 'Name',
                'selected_by_default' => true,
            ],
            'contact_name' => [
                'label' => 'Contact Name',
            ],
            'contact_email' => [
                'label' => 'Contact Email',
            ],
            'contact_phone' => [
                'label' => 'Contact Phone',
            ],
            'contact_address' => [
                'label' => 'Contact Address',
            ],
            'image' => [
                'label' => 'Image',
            ],
            'status' => [
                'label' => 'Status',
                'selected_by_default' => true,
            ],
            'created_at' => [
                'label' => 'Created At',
            ],
            'updated_at' => [
                'label' => 'Updated At',
            ],
        ];
    }

    /**
     */
    protected function resolveProductExportValue(Product $product, string $columnKey): string
    {
        return match ($columnKey) {
            'id' => (string) $product->id,
            'product_code' => (string) ($product->product_code ?? ''),
            'product_name' => (string) ($product->product_name ?? ''),
            'local_product_name' => (string) ($product->local_product_name ?? ''),
            'model_name' => (string) ($product->model_name ?? ''),
            'local_model_name' => (string) ($product->local_model_name ?? ''),
            'short_description' => (string) ($product->short_description ?? ''),
            'local_short_description' => (string) ($product->local_short_description ?? ''),
            'description' => (string) ($product->description ?? ''),
            'local_description' => (string) ($product->local_description ?? ''),
            'product_type' => (string) ($product->product_type ?? ''),
            'status' => (string) ($product->status ?? ''),
            'selling_price' => (string) ($product->selling_price ?? ''),
            'msrp' => (string) ($product->msrp ?? ''),
            'manufacturer' => (string) ($product->manufacturerRelation?->name ?? ''),
            'brand' => (string) ($product->brand?->title ?? ''),
            'categories' => (string) $product->categories
                ->pluck('category_name')
                ->filter()
                ->implode(', '),
            'product_classification' => (string) ($product->productClassification?->title ?? ''),
            'created_at' => optional($product->created_at)->format('Y-m-d H:i:s') ?? '',
            'updated_at' => optional($product->updated_at)->format('Y-m-d H:i:s') ?? '',
            default => '',
        };
    }

    protected function applyProductExportConditions(Builder $query, Request $request): Builder
    {
        $brandCondition = $request->input('brand_condition', 'any');
        $manufacturerCondition = $request->input('manufacturer_condition', 'any');
        $statusCondition = $request->input('status_condition', 'any');
        $hasSkuCondition = $request->input('has_sku_condition', 'any');

        if ($brandCondition === 'empty') {
            $query->whereNull('brand_id');
        } elseif ($brandCondition === 'not_empty') {
            $query->whereNotNull('brand_id');
        }

        if ($manufacturerCondition === 'empty') {
            $query->whereNull('manufacturer_id');
        } elseif ($manufacturerCondition === 'not_empty') {
            $query->whereNotNull('manufacturer_id');
        }

        if ($statusCondition !== 'any') {
            if ($statusCondition === 'archived') {
                $query->whereNotNull('archived_at');
            } else {
                $query->where('status', $statusCondition);
            }
        }

        if ($hasSkuCondition === 'yes') {
            $query->where('has_sku', true);
        } elseif ($hasSkuCondition === 'no') {
            $query->where('has_sku', false);
        }

        return $query;
    }

    protected function resolveManufacturerExportValue(Manufacturer $manufacturer, string $columnKey): string
    {
        return match ($columnKey) {
            'id' => (string) $manufacturer->id,
            'code' => (string) ($manufacturer->code ?? ''),
            'name' => (string) ($manufacturer->name ?? ''),
            'contact_name' => (string) ($manufacturer->contact_name ?? ''),
            'contact_email' => (string) ($manufacturer->contact_email ?? ''),
            'contact_phone' => (string) ($manufacturer->contact_phone ?? ''),
            'contact_address' => (string) ($manufacturer->contact_address ?? ''),
            'image' => (string) ($manufacturer->image ?? ''),
            'status' => $manufacturer->archived_at ? 'Archived' : 'Published',
            'created_at' => optional($manufacturer->created_at)->format('Y-m-d H:i:s') ?? '',
            'updated_at' => optional($manufacturer->updated_at)->format('Y-m-d H:i:s') ?? '',
            default => '',
        };
    }
}

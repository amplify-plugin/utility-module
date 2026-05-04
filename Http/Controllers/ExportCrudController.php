<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Abstracts\BackpackCustomCrudController;
use Amplify\System\Backend\Models\Manufacturer;
use Amplify\System\Backend\Models\Product;
use Amplify\System\Utility\Models\Export;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class ExportCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ExportCrudController extends BackpackCustomCrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    protected const SQL_EXPORT_DEFAULT_LIMIT = 100000;
    protected const SQL_EXPORT_MAX_LIMIT = 1000000;

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
        $this->crud->setListView('backend::pages.exports.workspace');
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

            $query = Product::query();

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

    public function previewSql(Request $request): JsonResponse
    {
        $rawSql = (string) $request->input('sql', '');
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $historyLabel = trim((string) $request->input('label', ''));

        $validation = $this->validateSelectSql($rawSql);
        if (! $validation['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $validation['message'],
                'columns' => [],
                'rows' => [],
            ], 422);
        }

        try {
            $previewSql = 'SELECT * FROM ('.$validation['sql'].') AS export_preview LIMIT '.$limit;
            $rows = array_map(
                static fn ($row) => (array) $row,
                DB::select($previewSql)
            );

            $historyItem = $this->saveSqlHistory($validation['sql'], $historyLabel);

            return response()->json([
                'valid' => true,
                'message' => 'SQL is valid. Preview generated successfully.',
                'columns' => ! empty($rows) ? array_keys($rows[0]) : [],
                'rows' => $rows,
                'history' => $historyItem,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'valid' => false,
                'message' => $exception->getMessage(),
                'columns' => [],
                'rows' => [],
            ], 422);
        } catch (\Throwable $exception) {
            return response()->json([
                'valid' => false,
                'message' => $exception->getMessage(),
                'columns' => [],
                'rows' => [],
            ], 422);
        }
    }

    public function downloadSql(Request $request): StreamedResponse
    {
        $rawSql = (string) $request->input('sql', '');
        $requestedLimit = (int) $request->input('export_limit', self::SQL_EXPORT_DEFAULT_LIMIT);
        $rowLimit = max(1, min(self::SQL_EXPORT_MAX_LIMIT, $requestedLimit));

        $validation = $this->validateSelectSql($rawSql);
        if (! $validation['valid']) {
            abort(422, $validation['message']);
        }

        $fileName = 'sql-export-'.now()->format('Y-m-d_His').'.csv';
        $wrappedSql = 'SELECT * FROM ('.$validation['sql'].') AS export_source LIMIT '.$rowLimit;

        return response()->streamDownload(function () use ($wrappedSql) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            DB::connection()->disableQueryLog();

            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            $statement = DB::connection()->getPdo()->prepare($wrappedSql);
            $statement->execute();

            $headerWritten = false;
            $rowCounter = 0;
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                if (! $headerWritten) {
                    fputcsv($output, array_keys($row));
                    $headerWritten = true;
                }

                fputcsv($output, array_values($row));
                $rowCounter++;

                // Periodically flush output to keep memory usage and buffers low on very large exports.
                if ($rowCounter % 1000 === 0) {
                    fflush($output);
                }
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function sqlHistory(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 4)));

        $query = Export::query();
        if ($this->hasExportColumn('type')) {
            $query->where('type', 'sql');
        }

        if ($this->hasExportColumn('query_text')) {
            $query->whereNotNull('query_text');
            $selectColumns = ['id', 'name', 'query_text', 'updated_at'];
        } else {
            $selectColumns = ['id', 'name', 'updated_at'];
        }

        if ($this->hasExportColumn('last_used_at')) {
            $query->orderByDesc('last_used_at');
            $selectColumns[] = 'last_used_at';
        }

        $paginator = $query
            ->orderByDesc('updated_at')
            ->paginate($perPage, $selectColumns)
            ->appends($request->query());

        $items = collect($paginator->items())
            ->map(function (Export $item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'query' => $item->query_text ?: $item->name,
                    'last_used_at' => $item->last_used_at ? Carbon::parse($item->last_used_at)->diffForHumans() : null,
                ];
            })
            ->values();

        return response()->json([
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function deleteSqlHistory(int $id): JsonResponse
    {
        $query = Export::query();
        if ($this->hasExportColumn('type')) {
            $query->where('type', 'sql');
        }

        $record = $query->findOrFail($id);

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'History query removed successfully.',
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
        $product = new Product;
        $table = $product->getTable();
        $columnNames = Schema::getColumnListing($table);
        $defaultSelectedColumns = ['id', 'product_code', 'product_name', 'status', 'selling_price'];

        $columns = [];
        foreach ($columnNames as $columnName) {
            $columns[$columnName] = [
                'label' => $this->formatExportLabel($columnName),
            ];

            if (in_array($columnName, $defaultSelectedColumns, true)) {
                $columns[$columnName]['selected_by_default'] = true;
            }
        }

        return $columns;
    }

    /**
     * @return array<string, array{label: string, selected_by_default?: bool}>
     */
    protected function manufacturerExportColumns(): array
    {
        $manufacturer = new Manufacturer;
        $table = $manufacturer->getTable();
        $columnNames = Schema::getColumnListing($table);
        $defaultSelectedColumns = ['id', 'code', 'name'];

        $columns = [];
        foreach ($columnNames as $columnName) {
            $columns[$columnName] = [
                'label' => $this->formatExportLabel($columnName),
            ];

            if (in_array($columnName, $defaultSelectedColumns, true)) {
                $columns[$columnName]['selected_by_default'] = true;
            }
        }

        return $columns;
    }

    /**
     */
    protected function resolveProductExportValue(Product $product, string $columnKey): string
    {
        $value = $product->getAttribute($columnKey);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) ($value ?? '');
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
        $value = $manufacturer->getAttribute($columnKey);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) ($value ?? '');
    }

    /**
     * @return array{valid: bool, message: string, sql: string}
     */
    protected function validateSelectSql(string $sql): array
    {
        $cleanSql = trim($sql);

        if ($cleanSql === '') {
            return [
                'valid' => false,
                'message' => 'SQL query is required.',
                'sql' => '',
            ];
        }

        $cleanSql = preg_replace('/\/\*.*?\*\//s', '', $cleanSql) ?? $cleanSql;
        $cleanSql = preg_replace('/^\s*--.*$/m', '', $cleanSql) ?? $cleanSql;
        $cleanSql = trim($cleanSql);

        $statementSql = rtrim($cleanSql, " \t\n\r\0\x0B;");

        if ($statementSql === '') {
            return [
                'valid' => false,
                'message' => 'SQL query is empty after cleanup.',
                'sql' => '',
            ];
        }

        if (str_contains($statementSql, ';')) {
            return [
                'valid' => false,
                'message' => 'Only one SQL statement is allowed.',
                'sql' => '',
            ];
        }

        if (! preg_match('/^\s*(select|with)\b/i', $statementSql)) {
            return [
                'valid' => false,
                'message' => 'Only SELECT queries are supported.',
                'sql' => '',
            ];
        }

        if (preg_match('/\b(insert|update|delete|drop|alter|truncate|create|rename|replace|grant|revoke|call|set)\b/i', $statementSql)) {
            return [
                'valid' => false,
                'message' => 'Only SELECT queries are supported.',
                'sql' => '',
            ];
        }

        if (preg_match('/\binto\s+outfile\b/i', $statementSql)) {
            return [
                'valid' => false,
                'message' => 'INTO OUTFILE is not allowed.',
                'sql' => '',
            ];
        }

        return [
            'valid' => true,
            'message' => 'Valid SELECT query.',
            'sql' => $statementSql,
        ];
    }

    /**
     * @return array{id: int, name: string, query: string, last_used_at: string}
     */
    protected function saveSqlHistory(string $sql, string $label = ''): array
    {
        $providedLabel = trim($label);
        $normalizedSql = trim($sql);
        $hasQueryHash = $this->hasExportColumn('query_hash');
        $hasType = $this->hasExportColumn('type');
        $hasQueryText = $this->hasExportColumn('query_text');
        $hasLastUsedAt = $this->hasExportColumn('last_used_at');
        $queryHash = hash('sha256', mb_strtolower($normalizedSql));

        $baseQuery = Export::query();
        if ($hasType) {
            $baseQuery->where('type', 'sql');
        }

        $existingExport = $hasQueryHash
            ? (clone $baseQuery)->where('query_hash', $queryHash)->first()
            : ($hasQueryText
                ? (clone $baseQuery)->where('query_text', $normalizedSql)->first()
                : null);

        $finalLabel = $providedLabel !== ''
            ? Str::limit($providedLabel, 120, '...')
            : ($existingExport?->name ?: $this->generateDefaultHistoryLabel());

        $labelOwner = (clone $baseQuery)
            ->where('name', $finalLabel)
            ->first();

        if ($labelOwner && (! $existingExport || (int) $labelOwner->id !== (int) $existingExport->id)) {
            throw ValidationException::withMessages([
                'label' => __('Duplicate history label is not allowed. Please use another label.'),
            ]);
        }

        $export = $existingExport ?: new Export;

        $export->name = $finalLabel;
        if ($hasType) {
            $export->type = 'sql';
        }
        if ($hasQueryText) {
            $export->query_text = $normalizedSql;
        } elseif (! $hasQueryHash) {
            // Legacy fallback schema: keep query in name when query_text is unavailable.
            $export->name = $finalLabel.' | '.$normalizedSql;
        }
        if ($hasQueryHash) {
            $export->query_hash = $queryHash;
        }
        if ($hasLastUsedAt) {
            $export->last_used_at = now();
        }
        $export->save();

        return [
            'id' => (int) $export->id,
            'name' => (string) $export->name,
            'query' => (string) ($export->query_text ?: $normalizedSql),
            'last_used_at' => optional($export->last_used_at)->format('Y-m-d H:i:s') ?? '',
        ];
    }

    protected function generateDefaultHistoryLabel(): string
    {
        do {
            $label = 'SQL-'.now()->format('YmdHis').'-'.random_int(100, 999);
            $exists = Export::query()->where('name', $label)->exists();
        } while ($exists);

        return $label;
    }

    protected function hasExportColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::getColumnListing((new Export)->getTable());
        }

        return in_array($column, $columns, true);
    }

    protected function formatExportLabel(string $columnName): string
    {
        $label = Str::of($columnName)
            ->replace('_', ' ')
            ->title()
            ->toString();

        return str_replace(
            [' Id', ' Sku', ' Upc', ' Msrp', ' Url'],
            [' ID', ' SKU', ' UPC', ' MSRP', ' URL'],
            $label
        );
    }
}

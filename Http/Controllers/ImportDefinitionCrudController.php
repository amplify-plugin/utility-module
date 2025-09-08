<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Abstracts\BackpackCustomCrudController;
use Amplify\System\Backend\Http\Requests\ImportDefinitionRequest;
use Amplify\System\Backend\Models\Permission;
use Amplify\System\Imports\ImportJobImport;
use Amplify\System\Utility\Models\ImportDefinition;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\InlineCreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Pro\Http\Controllers\Operations\FetchOperation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Class ImportDefinitionCrudController
 *
 * @property-read CrudPanel $crud
 */
class ImportDefinitionCrudController extends BackpackCustomCrudController
{
    use CreateOperation {
        store as traitStore;
    }
    use DeleteOperation;
    use FetchOperation;
    use InlineCreateOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation {
        update as traitUpdate;
    }

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(ImportDefinition::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/import-definition');
        CRUD::setEntityNameStrings('import-definition', 'import definitions');
    }

    /**
     * @return RedirectResponse
     */
    public function store(ImportDefinitionRequest $request)
    {
        $response = $this->traitStore($request);
        $this->removeFilesFromStorage();

        return $response;
    }

    protected function removeFilesFromStorage(): void
    {
        // Cleaning previous files
        $files = Storage::allFiles('public/import_files/temp');
        if (count($files) > 0) {
            Storage::delete($files);
        }
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function update(ImportDefinitionRequest $request)
    {
        $response = $this->traitUpdate($request);
        $this->removeFilesFromStorage();

        return $response;
    }

    public function fetchColumnListing(Request $request): JsonResponse
    {
        $data = $this->getColumns($request->importType);

        return response()->json($data, 200);
    }

    public function handleUploadFile(): JsonResponse
    {
        $filenameWithExt = request()->file->getClientOriginalName();
        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        $extension = request()->file->getClientOriginalExtension();
        $fileNameToStore = $filename.'_'.time().'.'.$extension;

        // Cleaning previous files
        $files = Storage::allFiles('public/import_files/temp');
        if (count($files) > 0) {
            Storage::delete($files);
        }

        // Uploading new file
        $path = request()->file->storeAs('public/import_files/temp', $fileNameToStore);
        $fileData = Excel::toCollection(new ImportJobImport, $path, 'local', \Maatwebsite\Excel\Excel::CSV);

        if ((bool) request()->is_column_heading) {
            // Get file data in array
            $data = collect($fileData->first())->take(11)->toArray();
            $header_rows = $data[0];
            unset($data[0]);
        } else {
            $data = collect($fileData->first())->take(10)->toArray();
            $number_of_header_rows = count($data[0]);
            $headerData = [];
            for ($x = 1; $x <= $number_of_header_rows; $x++) {
                $headerData[] = 'Column '.$x;
            }
            $header_rows = $headerData;
        }

        $product_data = array_values($data);

        $db_columns = $this->getColumns(request()->import_type);

        $attributes = getAllAttributes();

        $tables_name = config('import.tables_name');

        return response()->json([
            'header_rows' => $header_rows,
            'data' => $product_data,
            'db_columns' => $db_columns,
            'attributes' => $attributes,
            'tables_name' => $tables_name,
        ]);
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
        CRUD::column('id')->label('Id');
        CRUD::column('file_type')->label('File Type');
        CRUD::column('name')->label('Name');
        CRUD::addColumn([
            'name' => 'import_type',
            'label' => 'Import Type',
            'type' => 'custom_html',
            'value' => function ($importDefinition) {
                foreach (ImportDefinition::IMPORT_TYPES as $importType) {
                    if ($importDefinition->import_type == $importType['value']) {
                        return $importType['title'];
                    }
                }

                return 'N/A';
            },
        ]);
        CRUD::column('updated_at')->type('datetime')->label('Last Changed');
    }

    protected function setupInlineCreateOperation()
    {
        CRUD::setValidation(ImportDefinitionRequest::class);
        $this->data['translatable'] = array_keys($this->crud->model->translations);
        $this->formFields();

        /**
         * Fields can be defined using the fluent syntax or array syntax:
         * - CRUD::field('price')->type('number');
         * - CRUD::addField(['name' => 'price', 'type' => 'number']));
         */
    }

    /**
     * return form fields
     */
    protected function formFields(): void
    {
        CRUD::field('name')->type('text')->label('Name');
        CRUD::field('file_type')->type('enum')->label('File Type');
        CRUD::field('import_type')->type('enum')->label('Import Type');
        CRUD::field('file');
        CRUD::field('description');
        CRUD::field('is_column_heading');
        CRUD::field('has_hierarchy');
        CRUD::field('column_mapping');
        CRUD::field('import_file_field');
        CRUD::field('import_type_field');
        CRUD::field('required_fields');
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     *
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $importDefinitionData = $this->crud->getCurrentEntry();
        $this->data['importDefinitionData'] = $importDefinitionData;
        $this->data['dbColumns'] = $this->getColumns($importDefinitionData->import_type);
        $this->data['attributes'] = getAllAttributes();
        $this->data['tables_name'] = config('import.tables_name');

        $this->crud->setUpdateView('crud::pages.import.import-definition.create');
        $this->setupCreateOperation();
    }

    public function getColumns($importType): array
    {
        return match ($importType) {
            'ContactPermissions' => $this->generateContactPermissionColumns(),
            'Product' => $this->generateProductColumns($importType),
            default => $this->getDBColumns($importType)
        };
    }

    public function generateContactPermissionColumns(): array
    {
        $columns = [
            [
                'name' => 'customer_code',
                'type' => 'varchar(255)',
                'is_required' => false,
            ], [
                'name' => 'email',
                'type' => 'varchar(255)',
                'is_required' => true,
            ],
        ];

        $permissions = Permission::select('name')
            ->where('guard_name', 'customer')
            ->get()
            ->map(fn ($item) => [
                'name' => $item->name,
                'type' => 'tinyint(1)',
                'is_required' => false,
            ])
            ->toArray();

        $columns = [...$columns, ...$permissions];

        return $columns;
    }

    public function generateProductColumns($importType): array
    {
        $dbColumns = $this->getDBColumns($importType);
        $columns = [
            [
                'name' => 'parent_id',
                'type' => 'bigint',
                'is_required' => false,
            ],
        ];
        $columns = [...$columns, ...$dbColumns];

        return $columns;
    }

    public function getDBColumns($importType): array
    {
        $model = "\App\Models\\$importType";
        $ignoreId = false;
        if ($importType === 'CategoryProduct') {
            $ignoreId = true;
        }

        $allColumns = array_values(getColumnListing((new $model)->getTable(), true, [], $ignoreId));

        /* For attributes table, rename name and slug field */
        if ($importType === 'Attribute') {
            foreach ($allColumns as $key => $column) {
                if ($column['name'] === 'name') {
                    $allColumns[$key]['name'] = 'display_name';
                }

                if ($column['name'] === 'slug') {
                    $allColumns[$key]['name'] = 'name';
                    $allColumns[$key]['is_required'] = true;
                }
            }
        }

        return $allColumns;
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     *
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(ImportDefinitionRequest::class);
        // $this->data['translatable'] = array_keys($this->crud->model->translations);
        $this->data['all_hierarchies'] = getHierarchies();
        $this->crud->setCreateView('crud::pages.import.import-definition.create');
        $this->formFields();
    }

    protected function setupShowOperation()
    {
        $this->crud->setShowContentClass('col-md-12');
        CRUD::addColumn([
            'name' => 'user_id',
            'type' => 'custom_html',
            'value' => function ($model) {
                return $model->user->name;
            },
        ]);
        CRUD::column('name');
        CRUD::column('file_type');
        CRUD::column('import_type');
        CRUD::column('description');
        CRUD::addColumn([
            'name' => 'is_column_heading',
            'label' => 'Has Column Heading',
            'type' => 'custom_html',
            'value' => function ($model) {
                return $model->is_column_heading
                    ? 'Yes'
                    : 'No';
            },
        ]);
        CRUD::addColumn([
            'name' => 'column_mapping',
            'type' => 'custom_html',
            'value' => function ($model) {
                $script = '<script src="'.mix('/js/app.js').'"></script>';

                $data =
                    '<div class="rounded border" style="max-height: 350px !important; overflow-y: auto !important;"><table class="table mb-0">';
                $data .= '<thead>
                            <tr>
                                <th>Column Name</th>
                                <th>Map To</th>
                                <th>Field / Attribute / Table Name</th>
                                <th>Table Field Name</th>
                                <th>Separator</th>
                                <th>Attribute Value</th>
                            </tr>
                          </thead>';

                $data .= '<tbody>';
                foreach (json_decode($model->column_mapping) as $column_mapping) {
                    $data .= "<tr>
                                <td>$column_mapping->column_name</td>
                                <td>$column_mapping->map_to</td>


                                <td>
                            ";
                    $data .= is_array($column_mapping->field_or_attribute_name) ? implode(', ', $column_mapping->field_or_attribute_name) : $column_mapping->field_or_attribute_name;
                    $data .= "</td>
                                <td>$column_mapping->field_name</td>
                                <td>$column_mapping->separator</td>
                                <td>$column_mapping->attribute_value</td>
                            </tr>";
                }

                $data .= '</tbody>';

                $data .= '</table></div>';

                // $data .= $script;
                return $data;
            },
        ]);

        CRUD::addColumn([
            'name' => 'required_fields',
            'type' => 'custom_html',
            'value' => function ($model) {
                if (empty(json_decode($model->required_fields))) {
                    return '-';
                }

                $data = '<div class="rounded border tableFixHead"><table class="table mb-0">';
                $data .= '<thead>
                            <tr>
                                <th>Name</th>
                                <th>Is Required ?</th>
                            </tr>
                          </thead>';

                $data .= '<tbody>';
                foreach (json_decode($model->required_fields) as $required_field) {
                    $data .= "<tr><td>$required_field->name</td>";
                    $data .= $required_field->is_checked
                        ? '<td>Yes</td></tr>'
                        : '<td>No</td></tr>';
                }
                $data .= '</tbody>';

                $data .= '</table></div>';

                return $data;
            },
        ]);

        CRUD::column('created_at')->type('datetime')->label('Created At');
        CRUD::column('updated_at')->type('datetime')->label('Last Changed');
    }
}

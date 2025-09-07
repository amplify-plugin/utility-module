<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Abstracts\BackpackCustomCrudController;
use Amplify\System\Backend\Http\Requests\ImportJobRequest;
use Amplify\System\Jobs\ParentImportJob;
use Amplify\System\Utility\Models\ImportDefinition;
use Amplify\System\Utility\Models\ImportJob;
use Amplify\System\Utility\Traits\ImportJobTrait;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Pro\Http\Controllers\Operations\FetchOperation;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Class ImportJobCrudController
 *
 * @property-read CrudPanel $crud
 */
class ImportJobCrudController extends BackpackCustomCrudController
{
    use CreateOperation {
        store as traitStore;
    }
    use FetchOperation;
    use ImportJobTrait;
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
        CRUD::setModel(ImportJob::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/import-job');
        CRUD::setEntityNameStrings('import-job', 'import jobs');
        $this->crud->allowAccess('show');
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
        $this->crud->with('importErrors');

        CRUD::removeButton('delete');

        $this->crud->allowAccess('show');

        CRUD::addColumn([
            'name' => 'id',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $entity->created_at->diffForHumans();
            },
        ]);

        CRUD::addColumn([
            'name' => 'import_definition_id',
            'label' => 'Import Definition',
            'entity' => 'importDefinition',
            'attribute' => 'name',
            'model' => ImportDefinition::class,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('importDefinition', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);

        CRUD::column('locale');

        CRUD::addColumn([
            'name' => 'status',
            'Label' => 'Status',
            'type' => 'custom_html',
            'value' => function ($entity) {
                $state = $this->getState($entity);

                return "<i class='la ".$state['className']."' title='".$state['title']."'></i>";
            },
        ]);

        CRUD::addColumn([
            'name' => 'row_count',
            'label' => 'Row Count',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $this->getCount($entity, 'rows');
            },
        ]);

        CRUD::addColumn([
            'name' => 'success_count',
            'label' => 'Success Count',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $this->getCount($entity, 'success');
            },
        ]);

        CRUD::addColumn([
            'name' => 'failed_count',
            'label' => 'Failed Count',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $this->getCount($entity, 'failed');
            },
        ]);

        $this->crud->addButtonFromModelFunction('line', 'retry_import_job', 'retryImportJob', 'ending');
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
        CRUD::setValidation(ImportJobRequest::class);

        $this->data['importDefinition'] = $this->fetchImportDefinition()->getData();

        $this->crud->setCreateView('crud::pages.import.create');

        CRUD::addField([
            'name' => 'import_definition_id',
            'entity' => 'importDefinition',
            'label' => 'Import Definition',
            'inline_create' => true,
        ]);

        CRUD::field('file_path');
        CRUD::field('schedule_time');
        CRUD::field('chunk_size');
        CRUD::field('row_count');
        CRUD::field('locale');
        CRUD::field('description');

        /**
         * Fields can be defined using the fluent syntax or array syntax:
         * - CRUD::field('price')->type('number');
         * - CRUD::addField(['name' => 'price', 'type' => 'number']));
         */
    }

    /**
     * Save the specified resource in the database.
     *
     * @return RedirectResponse
     */
    public function store(ImportJobRequest $request)
    {
        $delay = 0;
        if (! empty($request->schedule_time)) {
            $request->merge([
                'schedule_time' => Carbon::parse($request->schedule_time)->format(getDefaultDateTimeFormat()),
            ]);

            $delay = Carbon::now()->diffInSeconds($request->schedule_time);
        }

        // your additional operations before save here
        $response = $this->traitStore($request);

        if ($response['data']->row_count > $response['data']->chunk_size) {
            $filePath = explode('/', $response['data']->file_path);
            $filePath[count($filePath) - 1] = Str::slug(removeExtension($filePath[count($filePath) - 1]));
            $fileDirectory = strtolower(implode('/', $filePath));
            $fileExt = getFileExtension($response['data']->file_path);
            $getAllFilesFromDirectory = getFilesFromStorage($fileDirectory, $fileExt);

            foreach ($getAllFilesFromDirectory as $file) {
                $data = [
                    'import_job_id' => $response['data']->id,
                    'import_definition_id' => $response['data']->import_definition_id,
                    'user_id' => $response['data']->user_id,
                    'locale' => $response['data']->locale,
                    'file_path' => $file,
                ];

                // Dispatch the job to the queue with data and delay
                ParentImportJob::dispatch($data)->delay($delay);
            }
        } else {
            $request->merge([
                'import_job_id' => $response['data']->id,
                'user_id' => $response['data']->user_id,
                'locale' => $response['data']->locale,
            ]);

            // Dispatch the job to the queue with data and delay
            ParentImportJob::dispatch($request->all())->delay($delay);
        }

        return $response;
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
        $this->data['importJobData'] = $this->crud->getCurrentEntry();

        $this->crud->setUpdateView('crud::pages.import.create');

        $this->setupCreateOperation();
    }

    /**
     * Update the specified resource in the database.
     *
     * @return Response
     */
    public function update(ImportJobRequest $request)
    {
        $delay = 0;
        if (! empty($request->schedule_time)) {
            $request->merge([
                'schedule_time' => Carbon::parse($request->schedule_time)->format(getDefaultDateTimeFormat()),
            ]);

            $delay = Carbon::now()->diffInSeconds($request->schedule_time);
        }

        $response = $this->traitUpdate($request);

        if ($request->retry) {
            $this->retryImportJob($request->id, $delay, true);
        }

        return $response;
    }

    /**
     * @return void
     */
    protected function setupShowOperation()
    {
        $this->crud->set('show.contentClass', 'col-md-12');

        // CRUD::removeButton('update');
        CRUD::removeButton('delete');
        $this->crud->addButtonFromModelFunction('line', 'retry_import_job', 'retryImportJob', 'ending');

        $this->crud->set('show.setFromDb', false);
        $this->crud->with('importErrors', 'importDefinition');

        CRUD::addColumn([
            'name' => 'id',
        ]);

        CRUD::addColumn([
            'name' => 'import_definition_id',
            'label' => 'Import Definition',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return ImportDefinition::query()->find($entry->import_definition_id)->local_name ?? '';
            },
        ]);

        CRUD::addColumn([
            'name' => 'file_path',
            'label' => 'File Path',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return getDownloadAnchorTag('storage/'.$entity->file_path);
            },
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'Label' => 'Status',
            'type' => 'custom_html',
            'value' => function ($entity) {
                $state = $this->getState($entity);

                return "<i class='la ".$state['className']."' title='".$state['title']."'></i>";
            },
        ]);

        CRUD::column('schedule_time');

        CRUD::addColumn([
            'name' => 'row_count',
            'label' => 'Row Count',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $this->getCount($entity, 'rows');
            },
        ]);

        CRUD::addColumn([
            'name' => 'success_count',
            'label' => 'Success Count',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $this->getCount($entity, 'success');
            },
        ]);

        CRUD::addColumn([
            'name' => 'failed_count',
            'label' => 'Failed Count',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $this->getCount($entity, 'failed');
            },
        ]);

        CRUD::addColumn([
            'name' => 'locale',
            'label' => 'Locale',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return getFullLocaleName($entity->locale);
            },
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return ! empty($entity->description)
                    ? $entity->description
                    : '-';
            },
        ]);

        CRUD::addColumn([
            'name' => 'user_id',
            'label' => 'User',
            'entity' => 'user',
        ]);

        CRUD::addColumn([
            'name' => 'errors',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $str = '-';

                if ($entry->status === 'failed') {
                    $str = ($message = json_decode($entry->errors)[0]->message ?? null)
                        ? '<p class="bg-dark text-warning p-3 rounded mb-0" style="font-family:consolas,sans-serif">'
                        .$message.'</p>'
                        : 'No error found!';
                }

                if ($entry->importErrors->count() > 0) {
                    $importErrors = $this->paginate(json_decode($entry->importErrors), 10)
                        ->withPath(\Request::getPathInfo());

                    $str = '<div data-retry-failed-job-url="'.backpack_url('/import-job/retry/failed-job').'"
                                data-update-failed-job-url="'.backpack_url('/import-job/update/failed-job').'"
                                data-error-job-id="'.$entry->id.'">
                              <div class="rounded border tableFixHead mb-3">
                                <table class="table m-0">
                                  <thead>
                                    <tr>
                                      <th class="v-middle">#</th>
                                      <th class="v-middle">'.($entry->importDefinition->import_type ?? '')
                        .' <span id="firstKeyPlace">ID</span>
                                      </th>';

                    $str .= '<th class="v-middle">Message</th>
                             <th class="v-middle">Failed At</th>
                             <th class="v-middle">Actions</th>
                           </tr>
                         </thead>
                         <tbody>';

                    foreach ($importErrors as $key => $error) {
                        $errorImportData = json_decode($error->import_data);
                        $firstVal = collect($errorImportData)->first();
                        $firstKey = collect($errorImportData)->keys()->first();
                        $error_created_at = Carbon::parse($error->created_at);

                        $str .= '<tr>
                                    <td class="v-middle"><strong>'.((int) $key + 1).'</strong></td>
                                    <td class="v-middle">'.$firstVal ?? "No $firstKey Found".'</td>';

                        $str .= '<td class="v-middle">
                                    <p class="bg-dark text-warning px-2 py-1 rounded mb-0 small" style="font-family:consolas,sans-serif">'
                            .htmlentities($error->error_message)
                            .'</p>
                                </td>
                                <td class="v-middle" title="'.$error_created_at->diffForHumans().'">
                                '.str_replace(' +0000', '', $error_created_at->format('r')).'
                                </td>
                                <td class="v-middle">
                                    <div class="btn-group" data-uuid="'.$error->uuid.'">
                                        <button class="btn btn-warning btn-sm" title="View this job details"
                                        data-import="'.htmlspecialchars($error->import_data).'"
                                        data-toggle="modal" data-target="#error-job-show">View</button>
                                        <button class="btn btn-secondary btn-sm retry-failed-job"
                                                id="retry-failed-job-'.$firstVal.'" title="Retry this job">
                                            Retry
                                        </button>
                                    </div>
                                </td>
                            </tr>';
                    }
                    $str .= '</tbody></table></div>'.$importErrors->links('pagination::bootstrap-4').'</div>';
                    $str .= '<div data-first-key="'.ucfirst($firstKey ?? 'ID').'"></div>';
                }

                return $str;
            },
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $entity->created_at->diffForHumans();
            },
        ]);

        CRUD::addColumn([
            'name' => 'updated_at',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $entity->updated_at->diffForHumans();
            },
        ]);
    }
}

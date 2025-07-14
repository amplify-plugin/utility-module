<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Utility\Models\DataTransformation;
use Amplify\System\Utility\Traits\DataTransformationJobTrait;
use Amplify\System\Utility\Traits\DataTransformationTrait;
use Amplify\System\Abstracts\BackpackCustomCrudController;
use Amplify\System\Helpers\DataTransformationHelper;
use App\Http\Requests\DataTransformationRequest;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Pro\Http\Controllers\Operations\FetchOperation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Class DataTransformationCrudController
 *
 * @property-read CrudPanel $crud
 */
class DataTransformationCrudController extends BackpackCustomCrudController
{
    use CreateOperation;
    use DataTransformationJobTrait;
    use DataTransformationTrait;
    use DeleteOperation {
        destroy as traitDestroy;
    }
    use FetchOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(DataTransformation::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/data-transformation');
        CRUD::setEntityNameStrings('data-transformation', 'data transformations');
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
        CRUD::column('id')->type('number')->thousands_sep('');
        CRUD::addColumn([
            'name' => 'transformation_name',
            'label' => 'Transformation Name',
        ]);
        CRUD::addColumn([
            'name' => 'applies_to',
            'label' => 'Applies To',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return json_decode($entity->applies_to)->name;
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

        CRUD::addColumn([
            'name' => 'row_count',
            'label' => 'Row Count',
        ]);

        CRUD::addColumn([
            'name' => 'success_count',
            'label' => 'Success Count',
        ]);

        CRUD::addColumn([
            'name' => 'failed_count',
            'label' => 'Failed Count',
        ]);

        CRUD::addColumn([
            'name' => 'in_category',
            'label' => 'In Category',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $this->getInCategories($entity);
            },
        ]);

        CRUD::addColumn([
            'name' => 'execution_sequence',
            'label' => 'Execution Sequence',
        ]);

        CRUD::addColumn([
            'name' => 'run_when',
            'label' => 'Run When',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $this->getRunWhen($entity);
            },
        ]);

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number']);
         */
        $this->crud->addButtonFromModelFunction('line', 'test_data_transformation_script', 'testDataTransformationScript', 'ending');
        $this->crud->addButtonFromModelFunction('line', 'run_data_transformation_script', 'runDataTransformationScript', 'ending');
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
        CRUD::setValidation(DataTransformationRequest::class);

        $this->data['appliesToOptions'] = DataTransformationHelper::getAppliesToOptions();
        $this->data['runWhenOptions'] = DataTransformationHelper::getRunWhenOptions();

        $this->crud->setCreateView('crud::pages.data_transformation.create');

        CRUD::addField([
            'name' => 'transformation_name',
            'label' => 'Transformation Name',
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => 'Description',
        ]);

        CRUD::addField([
            'name' => 'applies_to',
            'label' => 'Applies To',
        ]);

        CRUD::addField([
            'name' => 'in_category',
            'label' => 'In Category',
        ]);

        CRUD::addField([
            'name' => 'execution_sequence',
            'label' => 'Execution Sequence',
        ]);

        CRUD::addField([
            'name' => 'run_when',
            'label' => 'Run When',
        ]);

        CRUD::addField([
            'name' => 'scripts',
            'label' => 'Scripts',
        ]);

        CRUD::addField([
            'name' => 'file_path',
            'label' => 'File Path',
        ]);

        /**
         * Fields can be defined using the fluent syntax or array syntax:
         * - CRUD::field('price')->type('number');
         * - CRUD::addField(['name' => 'price', 'type' => 'number']));
         */
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
        $this->data['dataTransformationData'] = $this->crud->getCurrentEntry();

        $this->crud->setUpdateView('crud::pages.data_transformation.create');

        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        $this->crud->set('show.setFromDb', false);
        $this->crud->setShowContentClass('col-md-12');

        CRUD::addColumn([
            'name' => 'transformation_name',
            'label' => 'Transformation Name',
        ]);
        CRUD::addColumn([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return ! empty($entity->description)
                    ? $entity->description
                    : '-';
            },
        ]);
        CRUD::addColumn([
            'name' => 'applies_to',
            'label' => 'Applies To',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return json_decode($entity->applies_to)->name;
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

        CRUD::addColumn([
            'name' => 'row_count',
            'label' => 'Row Count',
        ]);

        CRUD::addColumn([
            'name' => 'success_count',
            'label' => 'Success Count',
        ]);

        CRUD::addColumn([
            'name' => 'failed_count',
            'label' => 'Failed Count',
        ]);

        CRUD::addColumn([
            'name' => 'in_category',
            'label' => 'In Category',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $this->getInCategories($entity);
            },
        ]);
        CRUD::addColumn([
            'name' => 'execution_sequence',
            'label' => 'Execution Sequence',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return ! empty($entity->execution_sequence)
                    ? $entity->execution_sequence
                    : '-';
            },
        ]);
        CRUD::addColumn([
            'name' => 'run_when',
            'label' => 'Run When',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return $this->getRunWhen($entity);
            },
        ]);

        CRUD::addColumn([
            'name' => 'scripts',
            'label' => 'Script',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return ! empty($entity->scripts)
                    ? "<textarea disabled='true'  class='form-control' rows='10' style='background-color: #0c1021; color: #fff;font-family: Monospace, monospace; border-color: transparent; box-shadow: none'>"
                    .$entity->scripts
                    .'</textarea>'
                    : '-';
            },
        ]);

        CRUD::addColumn([
            'name' => 'errors',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $str = '-';

                if ($entry->status === 'failed') {
                    $str = ($message = $entry->errors ?? null)
                        ? '<p class="bg-dark text-warning p-3 rounded mb-0" style="font-family:consolas">'.$message.'</p>'
                        : 'No error found!';
                }

                if ($entry->dataTransformationErrors->count() > 0) {
                    $dataTransformationErrors = $this->paginate(json_decode($entry->dataTransformationErrors), 10)->withPath(\Request::getPathInfo());

                    $str = '<div data-retry-failed-job-url="'.url('/admin/data-transformation/retry/failed-job').'"
                                data-update-failed-job-url="'.url('/admin/data-transformation/update/failed-job').'"
                                data-error-job-id="'.$entry->id.'">

                                <div class="rounded border tableFixHead mb-3" style="max-height: 500px !important;">
                                    <table class="table m-0">
                                        <thead>
                                            <tr>
                                                <th class="v-middle">#</th>
                                                <th class="v-middle"><span id="firstKeyPlace">ID</span></th>
                                                <th class="v-middle">Message</th>
                                                <th class="v-middle">Failed At</th>
                                                <th class="v-middle">Actions</th>
                                            </tr>
                                        </thead>
                                    <tbody>';

                    foreach ($dataTransformationErrors as $key => $error) {
                        $errorImportData = json_decode($error->data_transformation);
                        $firstVal = collect($errorImportData)->first();
                        $firstKey = collect($errorImportData)->keys()->first();
                        $error_created_at = Carbon::parse($error->created_at);

                        $str .= '<tr>
                                    <td class="v-middle"><strong>'.((int) $key + 1).'</strong></td>
                                    <td class="v-middle">'.$firstVal ?? "No $firstKey Found".'</td>';

                        $str .= '<td class="v-middle">
                                        <p class="bg-dark text-warning px-2 py-1 rounded mb-0 small" style="font-family:consolas">'.htmlentities($error->error_message).'</p>
                                    </td>
                                    <td class="v-middle" title="'.$error_created_at->diffForHumans().'">'.$error_created_at->format(getDefaultDateTimeFormat()).'</td>
                                    <td class="v-middle">
                                        <div class="btn-group" data-uuid="'.$error->uuid.'">
                                            <button class="btn btn-warning btn-sm" title="View this job details"
                                            data-import="'.htmlspecialchars($error->data_transformation).'"
                                            data-toggle="modal" data-target="#error-job-show">View</button>
                                            <button class="btn btn-secondary btn-sm retry-failed-job"
                                                    id="retry-failed-job-'.$firstVal.'" title="Retry this job">
                                                Retry
                                            </button>
                                        </div>
                                    </td>
                                </tr>';
                    }
                    $str .= '</tbody></table></div>'.$dataTransformationErrors->links('pagination::bootstrap-4').'</div>';
                    $str .= '<div data-first-key="'.ucfirst($firstKey ?? 'ID').'"></div>';
                }

                return $str;
            },
        ]);
    }

    private function getInCategories($entity): string
    {
        $view = '<div>';
        if (! empty($entity->in_category) && count(json_decode($entity->in_category)) > 0) {
            foreach (json_decode($entity->in_category) as $value) {
                $view .= '<span style="font-size: 15px;" class="badge badge-secondary">'
                    .DataTransformationHelper::getCategoryName($value)
                    .'</span>';
            }
        } else {
            $view .= '<span>-</span>';
        }

        return $view .= '</div>';
    }

    private function getRunWhen($entity): string
    {
        $view = '<div>';
        if (count($entity->run_when) > 0) {
            foreach ($entity->run_when as $value) {
                $view .= '<span style="font-size: 15px;" class="badge badge-secondary">'.$value.'</span>';
            }
        } else {
            $view .= '<span>-</span>';
        }

        return $view .= '</div>';
    }

    public function destroy($id): string
    {
        $this->crud->hasAccessOrFail('delete');
        $dataTransformation = $this->crud->getCurrentEntry();
        $isDeleted = $this->crud->delete($id);

        if (
            $isDeleted
            && ! empty($dataTransformation->file_path)
            && Storage::disk('local')->exists($dataTransformation->file_path)
        ) {
            Storage::disk('local')->delete($dataTransformation->file_path);
        }

        return $isDeleted;
    }
}

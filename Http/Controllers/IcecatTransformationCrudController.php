<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Abstracts\BackpackCustomCrudController;
use Amplify\System\Backend\Http\Requests\IcecatTransformationRequest;
use Amplify\System\Helpers\IcecatTransformationHelper;
use Amplify\System\Utility\Models\IcecatTransformation;
use Amplify\System\Utility\Traits\IcecatTransformationJobTrait;
use Amplify\System\Utility\Traits\IcecatTransformationTrait;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Pro\Http\Controllers\Operations\FetchOperation;
use Carbon\Carbon;

/**
 * Class IcecatTransformationCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class IcecatTransformationCrudController extends BackpackCustomCrudController
{
    use CreateOperation;
    use FetchOperation;
    use IcecatTransformationJobTrait;
    use IcecatTransformationTrait;
    use ListOperation;
    use ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(IcecatTransformation::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/icecat-transformation');
        CRUD::setEntityNameStrings('icecat-transformation', 'icecat transformations');
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

        CRUD::removeButtons(['update', 'delete']);

        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Transformation Name',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'icecat_definition',
            'label' => 'Transformation Definition',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return optional($entity->definition)->name;
            },
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Transformation Status',
            'type' => 'custom_html',
            'value' => function ($entity) {
                if ($entity->status == 'success') {
                    return "<i class='la la-check text-success'></i>";
                }
                if ($entity->status == 'pending') {
                    return "<i class='la la-sync la-pulse text-info'></i>";
                }
                if ($entity->status == 'failed') {
                    return "<i class='la la-check text-danger'></i>";
                }
            },
        ]);

        CRUD::addColumn([
            'name' => 'rows',
            'label' => 'Rows',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'success_count',
            'label' => 'Success Count',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'failed_count',
            'label' => 'Failed Count',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'type' => 'custom_html',
            'label' => 'Created At',
            'value' => function ($entry) {
                return date('d/m/Y h:iA', strtotime($entry->created_at));
            },
        ]);

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number']);
         */

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number']);
         */
    }

    private function getInCategories($entity): string
    {
        $view = '<div>';
        if (! empty($entity->in_category) && count(json_decode($entity->in_category)) > 0) {
            foreach (json_decode($entity->in_category) as $value) {
                $view .= '<span style="font-size: 15px;" class="badge badge-secondary">'
                         .IcecatTransformationHelper::getCategoryName($value)
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

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     *
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(IcecatTransformationRequest::class);

        $this->data['appliesToOptions'] = IcecatTransformationHelper::getAppliesToOptions();
        $this->data['runWhenOptions'] = IcecatTransformationHelper::getRunWhenOptions();
        $this->data['transformationNames'] = IcecatTransformationHelper::getTransformationNames();

        $this->crud->setCreateView('backend::pages.icecat_transformation.run_script');

        CRUD::addField([
            'name' => 'name',
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

    protected function setupShowOperation(): void
    {
        $this->crud->set('show.setFromDb', false);
        $this->crud->setShowContentClass('col-md-12');

        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Transformation Name',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'icecat_definition',
            'label' => 'Transformation Definition',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return optional($entity->definition)->name;
            },
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Transformation Status',
            'type' => 'custom_html',
            'value' => function ($entity) {
                if ($entity->status == 'success') {
                    return "<i class='la la-check text-success'></i>";
                }
                if ($entity->status == 'pending') {
                    return "<i class='la la-sync la-pulse text-info'></i>";
                }
                if ($entity->status == 'failed') {
                    return "<i class='la la-check text-danger'></i>";
                }
            },
        ]);

        CRUD::addColumn([
            'name' => 'rows',
            'label' => 'Rows',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'success_count',
            'label' => 'Success Count',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'failed_count',
            'label' => 'Failed Count',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'errors',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $str = '-';

                if ($entry->errors->count() > 0) {
                    $icecatTransformationErrors = $this->paginate($entry->errors, 10);

                    $str = '<div data-retry-failed-job-url="'.url('/admin/icecat-transformation/retry/failed-job').'"
                                data-update-failed-job-url="'.url('/admin/icecat-transformation/update/failed-job').'"
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

                    foreach ($icecatTransformationErrors as $key => $error) {
                        $errorIcecatData = json_decode($error->icecat_transformation);
                        $firstVal = collect($errorIcecatData)->first();
                        $firstKey = collect($errorIcecatData)->keys()->first();
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
                                            data-import="'.htmlspecialchars($error->icecat_transformation).'"
                                            data-toggle="modal" data-target="#error-job-show">View</button>
                                            <button class="btn btn-secondary btn-sm retry-failed-job"
                                                    id="retry-failed-job-'.$firstVal.'" title="Retry this job">
                                                Retry
                                            </button>
                                        </div>
                                    </td>
                                </tr>';
                    }
                    $str .= '</tbody></table></div>'.$icecatTransformationErrors->links('pagination::bootstrap-4').'</div>';
                    $str .= '<div data-first-key="'.ucfirst($firstKey ?? 'ID').'"></div>';
                }

                return $str;
            },
        ]);
    }
}

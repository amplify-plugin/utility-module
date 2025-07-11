<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Utility\Models\FailedJob;
use App\Abstracts\BackpackCustomCrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redirect;
use Prologue\Alerts\Facades\Alert;

/**
 * Class FailedJobCrudController
 *
 * @property-read CrudPanel $crud
 */
class FailedJobCrudController extends BackpackCustomCrudController
{
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(FailedJob::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/failed-job');
        CRUD::setEntityNameStrings('failed-job', 'failed jobs');
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
        $this->crud->allowAccess(['retry']);

        CRUD::addFilter(
            [
                'name' => 'name',
                'type' => 'dropdown',
                'label' => 'Job Name',
            ],
            function () {
                return FailedJob::all()->map(function ($item) {
                    return $item->payload;
                })->pluck('displayName', 'displayName')->toArray();
            },
            function ($value) {
                $this->crud->addClause('where', 'payload->displayName', '=', $value);
            }
        );

        CRUD::column('id')->type('number')->thousands_sep('');

        CRUD::column('queue');

        CRUD::addColumn([
            'name' => 'name',
            'type' => 'custom_html',
            'value' => function ($failedJob) {
                return $failedJob->payload['displayName'] ?? 'N/A';
            },
        ]);

        CRUD::column('failed_at');

        $this->crud->removeButton('update');

        $this->crud->addButtonFromView('line', 'retry', 'retry', 'beginning');
    }

    protected function setupShowOperation()
    {
        $this->crud->setShowContentClass('col-12');

        CRUD::column('id')->type('text');

        CRUD::column('uuid');

        CRUD::column('queue');

        CRUD::column('connection');

        CRUD::column('failed_at')->type('datetime');

        CRUD::addColumn([
            'name' => 'payload',
            'type' => 'custom_html',
            'label' => 'Payload',
            'value' => function ($failedJob) {
                $payload = $failedJob->payload;

                return view('crud::partials.job', compact('payload'))->render();
            },
        ]);

        CRUD::addColumn([
            'name' => 'exception',
            'type' => 'custom_html',
            'label' => 'Exception',
            'value' => function ($failedJob) {
                return "<div style='width:100%; overflow-x: scroll; padding: 1rem;'><pre>".$failedJob->exception.'</pre></div>';
            },
        ]);

        $this->crud->removeButton('update');

        $this->crud->addButtonFromView('line', 'retry', 'retry', 'beginning');
    }

    public function retry($id)
    {
        $failedJob = FailedJob::findOrFail($id);

        Artisan::call('queue:retry '.$failedJob->uuid);

        Alert::add('success', 'Add to queried again.')->flash();

        return Redirect::to($this->crud->route);
    }
}

<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Utility\Models\Job;
use Amplify\System\Abstracts\BackpackCustomCrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class JobCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class JobCrudController extends BackpackCustomCrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(Job::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/job');
        CRUD::setEntityNameStrings('job', 'jobs');
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
        CRUD::column('queue');
        CRUD::addColumn([
            'name' => 'name',
            'type' => 'custom_html',
            'value' => function ($failedJob) {
                return $failedJob->payload['displayName'] ?? 'N/A';
            },
        ]);
        CRUD::column('attempts');
        CRUD::column('reserved_at')->type('datetime');
        CRUD::column('available_at')->type('datetime');
        CRUD::column('created_at')->type('datetime');

        $this->crud->removeButton('update');
    }

    protected function setupShowOperation()
    {
        $this->crud->setShowContentClass('col-12');

        CRUD::column('id')->type('number')->thousands_sep('');
        CRUD::column('queue');
        CRUD::addColumn([
            'name' => 'name',
            'type' => 'custom_html',
            'value' => function ($failedJob) {
                return $failedJob->payload['displayName'] ?? 'N/A';
            },
        ]);
        CRUD::column('attempts');
        CRUD::column('reserved_at')->type('datetime');
        CRUD::column('available_at')->type('datetime');
        CRUD::column('created_at')->type('datetime');
        CRUD::addColumn([
            'name' => 'payload',
            'type' => 'custom_html',
            'label' => 'Payload',
            'value' => function ($failedJob) {
                $payload = $failedJob->payload;

                return view('backend::partials.job', compact('payload'))->render();
            },
        ]);

        $this->crud->removeButton('update');
    }
}

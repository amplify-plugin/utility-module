<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Utility\Models\ScheduledJob;
use Amplify\System\Utility\Traits\ScheduledJobTrait;
use Amplify\System\Abstracts\BackpackCustomCrudController;
use App\Http\Requests\ScheduledJobRequest;
use App\Models\User;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;

/**
 * Class ScheduledJobCrudController
 *
 * @property-read CrudPanel $crud
 */
class ScheduledJobCrudController extends BackpackCustomCrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\Pro\Http\Controllers\Operations\FetchOperation;
    use ScheduledJobTrait;

    /**
     * @var mixed
     */
    protected $payload;

    /**
     * @var mixed
     */
    protected $request;

    /**
     * @var mixed
     */
    protected $command;

    protected string $jobType;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(ScheduledJob::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/scheduled-job');
        CRUD::setEntityNameStrings('scheduled-job', 'scheduled jobs');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * Try to keep the JobType same as model name
     * i.e. if the model is App\Models\ImportJob, the job type is ImportJob
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     *
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('id')->type('number')->thousands_sep('');
        CRUD::removeButton('create');
        CRUD::removeButton('show');
        CRUD::removeButton('update');
        CRUD::removeButton('delete');

        $this->crud->orderBy('available_at');
        $this->crud->addClause('where', 'available_at', '>', Carbon::now()->timestamp);

        CRUD::addColumn([
            'name' => 'user',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $this->payload = json_decode($entry->payload);
                $this->command = unserialize($this->payload->data->command);

                /**
                 * Try to keep the JobType same as model name
                 * i.e. if the model is App\Models\ImportJob, the job type is ImportJob
                 */
                $this->jobType = $this->command->jobType ?? '';
                $this->request = method_exists($this->command, 'getRequest') ? $this->command->getRequest() : '';

                $user = User::query()->find($this->request['user_id'] ?? null);

                return $user->name ?? '-';
            },
        ]);

        CRUD::addColumn([
            'name' => 'job_name',
            'label' => 'Job Name',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return $this->getJobName();
            },
        ]);

        CRUD::addColumn([
            'name' => 'Locale',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return getFullLocaleName($this->request['locale']);
            },
        ]);

        CRUD::addColumn([
            'name' => 'file_path',
            'label' => 'File Path',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return isset($this->request['file_path']) && ! empty($this->request['file_path'])
                    ? getDownloadAnchorTag('storage/'.$this->request['file_path'])
                    : '-';
            },
        ]);

        CRUD::addColumn([
            'name' => 'available_at',
            'label' => 'Scheduled Time',
            'type' => 'custom_html',
            'value' => function ($entity) {
                $date = Carbon::parse($entity->available_at);
                $available_at = $date->format(getDefaultDateTimeFormat());
                $diffForHumans = $date->diffForHumans();

                return "<div title='$diffForHumans'>$available_at</div>";
            },
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'custom_html',
            'value' => function ($entity) {
                $date = Carbon::parse($entity->created_at);
                $created_at = $date->format(getDefaultDateTimeFormat());
                $diffForHumans = $date->diffForHumans();

                return "<div title='$diffForHumans'>$created_at</div>";
            },
        ]);

        CRUD::addColumn([
            'name' => 'Actions',
            'label' => 'Actions',
            'type' => 'custom_html',
            'value' => function ($entity) {
                return "<div class='btn-group btn-group-sm' role='group'>
                        <a href='/admin/scheduled-job/run-now/$entity->id' class='btn btn-primary' title='Run job now'> Run Now </a>
                    </div>";
            },
        ]);

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number']);
         */
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
        CRUD::setValidation(ScheduledJobRequest::class);

        CRUD::setFromDb(); // fields

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
        $this->setupCreateOperation();
    }
}

<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Utility\Models\MailLog;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
use Illuminate\Support\Facades\Route;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Support\Number;

/**
 * Class MailLogCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class MailLogCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(MailLog::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/mail-log');
        CRUD::setEntityNameStrings('mail-log', 'mail logs');

        if (!backpack_user()->isAdmin()) {
            $this->crud->denyAccess('list');
            $this->crud->denyAccess('show');
            $this->crud->denyAccess('delete');
        }
    }

    protected function setupCustomRoutes($segment, $routeName, $controller): void
    {
        Route::get($segment . '/preview-mail/{mailLog}', [
            'as' => $routeName . '.previewMail',
            'uses' => $controller . '@previewMail',
            'operation' => 'previewMail',
        ]);
    }

    private function widgets()
    {
        $stats = MailLog::query()
            ->selectRaw('count(*) as `total`')
            ->selectRaw("count(case when `status` = 'sending' then 1 end) as `sending`")
            ->selectRaw("count(case when `status` = 'sent' then 1 end) as `sent`")
            ->selectRaw("count(case when `status` = 'failed' then 1 end) as `failed`")
            ->when(request()->filled('status'), fn($query) => $query->where('status', request()->input('status')))
            ->when(request()->filled('email'), function ($query) {
                $value = request()->input('email');
                return $query->where('email', 'like', "%$value%")
                    ->orWhere('body', 'like', "%$value%");
            })->first();

        $percentSent = $stats->total > 0 ? (($stats->sent / $stats->total) * 100) : 0;
        $percentSending = $stats->total > 0 ? (($stats->sending / $stats->total) * 100) : 0;
        $percentFailed = $stats->total > 0 ? (($stats->failed / $stats->total) * 100) : 0;

        Widget::add([
            'type' => 'div',
            'class' => 'row my-3',
            'content' => [
                [
                    'wrapper' => ['class' => 'col-sm-6 col-lg-4'],
                    'type' => 'progress',
                    'class' => 'card text-white bg-success mb-2',
                    'value' => $stats->sent,
                    'description' => 'Successful',
                    'progress' => round($percentSent),
                    'hint' => $stats->sent > 0 ? Number::percentage($percentSent, 2) . ' messages successfully sent.' : 'No data available',
                ],
                [
                    'wrapper' => ['class' => 'col-sm-6 col-lg-4'],
                    'type' => 'progress',
                    'class' => 'card text-white bg-warning mb-2',
                    'value' => $stats->sending,
                    'description' => 'Not Acknowledged',
                    'progress' => round($percentSending),
                    'hint' => $stats->sending > 0 ? Number::percentage($percentSending, 2) . ' messages provider not acknowledged.' : 'No data available',
                ],
                [
                    'wrapper' => ['class' => 'col-sm-6 col-lg-4'],
                    'type' => 'progress',
                    'class' => 'card text-white bg-danger mb-2',
                    'value' => $stats->failed,
                    'description' => 'Failed',
                    'progress' => round($percentFailed),
                    'hint' => $stats->failed > 0 ? Number::percentage($percentFailed, 2) . ' messages failed with error.' : 'No data available',
                ],
            ]
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
        $this->crud->removeButtons(['create', 'update', 'delete']);

        CRUD::addFilter(
            [
                'name' => 'status',
                'type' => 'dropdown',
                'label' => 'Status',
            ],
            fn() => ['sent' => 'Sent', 'sending' => 'Sending', 'failed' => 'Failed'],
            fn($value) => $this->crud->addClause('where', 'status', '=', $value)
        );

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'email',
            'label' => 'Email',
        ],
            false,
            function ($value) { // if the filter is active
                $this->crud->addClause('where', 'email', 'like', "%$value%");
                $this->crud->addClause('orWhere', 'body', 'like', "%$value%");
            });

        $this->widgets();

        CRUD::column('id');
        CRUD::column('email')->label('To')->type('array');
        CRUD::column('status');
        CRUD::column('subject');
        CRUD::column('created_at');

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number']);
         */
    }

    protected function setupShowOperation()
    {
        $this->crud->removeButtons(['create', 'update', 'delete']);

        CRUD::column('email')->label('To')->type('array');
        CRUD::column('status');
        CRUD::column('subject');
        CRUD::column('body')->type('html-page');
        CRUD::column('data')->type('json')->wrapper(['element' => 'pre', 'style' => 'width: 78vw !important; height: 70vh; display:block; overflow: scroll;']);
        CRUD::column('created_at');
    }

    protected function previewMail(MailLog $mailLog)
    {
        return $mailLog->body;
    }
}

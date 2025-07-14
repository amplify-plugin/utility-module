<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Utility\Models\MailLog;
use Amplify\System\Abstracts\BackpackCustomCrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Route;

/**
 * Class MailLogCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class MailLogCrudController extends BackpackCustomCrudController
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
        CRUD::setModel(MailLog::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/mail-log');
        CRUD::setEntityNameStrings('mail-log', 'mail logs');

    }

    protected function setupCustomRoutes($segment, $routeName, $controller): void
    {
        Route::get($segment.'/preview-mail/{mailLog}', [
            'as' => $routeName.'.previewMail',
            'uses' => $controller.'@previewMail',
            'operation' => 'previewMail',
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

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'Email',
            'label' => 'Email',
        ],
            false,
            function ($value) { // if the filter is active
                $this->crud->addClause('where', 'email', 'ILIKE', "%$value%");
                $this->crud->addClause('orWhere', 'body', 'ILIKE', "%$value%");
            });

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

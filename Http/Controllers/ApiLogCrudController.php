<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Utility\Models\ApiLog;
use Amplify\System\Abstracts\BackpackCustomCrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ApiLogCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ApiLogCrudController extends BackpackCustomCrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;
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
        CRUD::setModel(ApiLog::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/api-log');
        CRUD::setEntityNameStrings('api-log', 'api logs');
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
        $erp_host = config('amplify.erp.configurations.'.config('amplify.erp.default').'.url');
        $erp_host = parse_url($erp_host, PHP_URL_HOST);

        $options = [
            'method' => [
                'GET' => 'GET',
                'POST' => 'POST',
                'PUT' => 'PUT',
                'PATCH' => 'PATCH',
                'DELETE' => 'DELETE',
                'OPTION' => 'OPTION',
            ],
            'group' => [
                config('amplify.sayt.dictionary.host') => config('amplify.sayt.dictionary.host'),
                config('amplify.report.host') => config('amplify.report.host'),
                $erp_host => $erp_host,
                'www.cenpos.net' => 'www.cenpos.net',
                'api.navigator.traceparts.com' => 'api.navigator.traceparts.com',
            ],
            'code' => [
                '200' => '200',
                '400' => '400',
                '500' => '500',
                '404' => '404',
                '422' => '422',
                '419' => '419',
            ],
        ];

        CRUD::addFilter(
            [
                'name' => 'group',
                'type' => 'dropdown',
                'label' => 'Group',
            ],
            function () use (&$options) {
                return $options['group'];
            },
            function ($value) {
                $this->crud->addClause('where', 'group', '=', $value);
            }
        );

        CRUD::addFilter(
            [
                'name' => 'method',
                'type' => 'dropdown',
                'label' => 'Method',
            ],
            function () use (&$options) {
                return $options['method'];
            },
            function ($value) {
                $this->crud->addClause('where', 'method', '=', $value);
            }
        );

        CRUD::addFilter(
            [
                'name' => 'status_code',
                'type' => 'dropdown',
                'label' => 'Status Code',
            ],
            function () use (&$options) {
                return $options['code'];
            },
            function ($value) {
                $this->crud->addClause('where', 'status_code', '=', $value);
            }
        );

        CRUD::addFilter(
            [
                'type' => 'date_range',
                'name' => 'created_at',
                'label' => 'Created Between',
            ],
            false,
            function ($value) {
                $value = json_decode($value);

                $this->crud->addClause('where', 'created_at', '>=', $value->from.' 00.00.01');
                $this->crud->addClause('where', 'created_at', '<=', $value->to.' 23:59:59');
            }
        );

        CRUD::removeButton('update');

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'wild_search',
            'label' => 'Wild Search',
        ],
            false,
            function ($value) {
                $this->crud->addClause('where', 'url', 'LIKE', "%$value%");
                $this->crud->addClause('orWhere', 'request_body', 'LIKE', "%$value%");
                $this->crud->addClause('orWhere', 'response_body', 'LIKE', "%$value%");
            });

        $this->crud->setOperationSetting('showEntryCount', false);

        CRUD::column('id')->type('number')->thousands_sep('');
        //        CRUD::column('group');
        CRUD::column('method');
        CRUD::column('url')->type('url')->limit(100)->searchLogic(function ($query, $column, $searchTerm) {
            return $query->orWhere($column['name'], 'like', strtolower("%{$searchTerm}%"));
        });

        CRUD::column('status_code')->type('number')->label('Code');
        //        CRUD::column('type');
        CRUD::column('created_at');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     *
     * @return void
     */
    protected function setupShowOperation()
    {
        CRUD::setShowContentClass('col-md-12');

        CRUD::column('group');
        CRUD::column('method');
        CRUD::column('url')->type('textarea');
        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'custom_html',
            'value' => function ($apiLog) {
                return $apiLog->status_code.' - '.$apiLog->status_text;
            }]);
        CRUD::column('type');
        CRUD::column('response_time')->suffix(' seconds')->label('Time');
        CRUD::column('request_header')->type('json');
        CRUD::column('request_body')->type('json');
        CRUD::column('response_header')->type('json');
        CRUD::column('response_body')->type('json');
        CRUD::column('created_at');
        CRUD::column('updated_at');
    }
}

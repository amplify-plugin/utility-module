<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Utility\Models\Audit;
use Amplify\System\Abstracts\BackpackCustomCrudController;
use App\Models\Contact;
use App\Models\User;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class AuditCrudController
 *
 * @property-read CrudPanel $crud
 */
class AuditCrudController extends BackpackCustomCrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(Audit::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/audit');
        CRUD::setEntityNameStrings('audit', 'activity logs');
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
                'name' => 'event',
                'type' => 'select2_multiple',
                'label' => 'Event',
            ],
            function () {
                return array_combine(config('audit.events'), config('audit.events'));
            },

            function ($value) {
                // if the filter is active
                $this->crud->addClause('whereIn', 'event', json_decode($value, true));
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

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'wild_search',
            'label' => 'Wild Search',
        ],
            false,
            function ($value) {
                $this->crud->addClause('where', 'user_type', 'like', strtolower("%$value%"));
                $this->crud->addClause('orWhere', 'auditable_type', 'like', strtolower("%$value%"));
                $this->crud->addClause('orWhere', 'old_values', 'like', strtolower("%$value%"));
                $this->crud->addClause('orWhere', 'new_values', 'like', strtolower("%$value%"));
                $this->crud->addClause('orWhere', 'user_agent', 'like', strtolower("%$value%"));

            });

        $this->crud->setOperationSetting('showEntryCount', false);

        CRUD::column('id')->label('#');

        CRUD::column('event')
            ->type('custom_html')
            ->searchLogic(function ($query, $column, $searchTerm) {
                return $query->orWhere($column['name'], 'like', strtolower("%{$searchTerm}%"));
            })
            ->value(function ($audit) {
                return ucfirst($audit->event);
            });

        CRUD::column('user_id')
            ->type('custom_html')
            ->value(function ($audit) {
                $auditUser = $audit->user;

                if ($auditUser instanceof User) {
                    return '<a href="'.route('user.showDetailsRow', $auditUser->id).'" class="font-weight-bold text-decoration-none">'.class_basename($auditUser).': '.$auditUser->name.'</a>';
                }

                if ($auditUser instanceof Contact) {
                    return '<a href="'.route('contact.show', $auditUser->id).'" class="font-weight-bold text-decoration-none">'.class_basename($auditUser).': '.$auditUser->name.'</a>';
                }

                return '-';
            });

        CRUD::column('auditable_id')
            ->type('custom_html')
            ->searchLogic(function ($query, $column, $searchTerm) {
                return $query->orWhere('auditable_type', 'like', strtolower("%{$searchTerm}%"));
            })
            ->label('Changed')
            ->value(function ($audit) {
                return '<a href="'.$audit->url.'" class="font-weight-bold text-info text-decoration-none">'.(($audit->auditable) ? class_basename($audit->auditable) : 'Unknown').'#'.(($audit->auditable) ? $audit->auditable->id : 'N/A').'</a>';
            });

        CRUD::column('ip_address')
            ->type('custom_html')
            ->searchLogic(function ($query, $column, $searchTerm) {
                return $query->orWhere('ip_address', 'like', strtolower("%{$searchTerm}%"));
            })
            ->value(function ($audit) {
                return '<a href="https://www.google.com/search?q='.($audit->ip_address ?? '-').'" target="_blank" class="text-decoration-none">'.($audit->ip_address ?? '-').'</a>';
            });

        CRUD::column('created_at')
            ->type('datetime');

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number']);
         */
    }

    protected function setupShowOperation()
    {
        $this->crud->removeButtons(['update', 'delete']);
        $this->crud->setShowContentClass('col-md-12');

        CRUD::column('id')->type('number')->thousands_sep('');

        CRUD::column('event')->type('custom_html')->value(function ($audit) {
            return ucfirst($audit->event);
        });

        CRUD::column('user')->type('custom_html')->value(function ($audit) {
            $auditUser = $audit->user;
            if ($auditUser instanceof User) {
                return '<a href="'.route('user.showDetailsRow', $auditUser->id).'" class="font-weight-bold text-decoration-none">'.$auditUser->name.':'.$auditUser->id.'</a>';
            } elseif ($auditUser instanceof Contact) {
                return '<a href="'.route('contact.show', $auditUser->id).'" class="font-weight-bold text-decoration-none">'.$auditUser->name.':'.$auditUser->id.'</a>';
            } else {
                return '-';
            }
        });

        CRUD::column('ip_address')->type('custom_html')->value(function ($audit) {
            return '<a href="https://www.google.com/search?q='.($audit->ip_address ?? '-').'" target="_blank" class="text-decoration-none">'.($audit->ip_address ?? '-').'</a>';
        });

        CRUD::column('auditable_id')->type('custom_html')->label('Changed')->value(function ($audit) {
            return '<a href="'.$audit->url.'" class="font-weight-bold text-info text-decoration-none">'.(($audit->auditable) ? class_basename($audit->auditable) : 'Unknown').':'.(($audit->auditable) ? $audit->auditable->id : 'N/A').'</a>';
        });

        CRUD::column('old_values')->type('json');

        CRUD::column('new_values')->type('json');

        CRUD::column('user_agent')->type('textarea');
        CRUD::column('created_at')->type('datetime');
    }
}

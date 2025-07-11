<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Utility\Models\IcecatDefinition;
use App\Abstracts\BackpackCustomCrudController;
use App\Http\Requests\IcecatDefinitionRequest;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Validation\ValidationException;

/**
 * Class IcecatDefinitionCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class IcecatDefinitionCrudController extends BackpackCustomCrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation {
        store as traitStore;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation {
        update as traitUpdate;
    }

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(IcecatDefinition::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/icecat-definition');
        CRUD::setEntityNameStrings('icecat-definition', 'icecat definitions');
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
            'name' => 'name',
            'label' => 'Name',
        ]);

        CRUD::addColumn([
            'name' => 'product_name',
            'label' => 'Product Name',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->product_name))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'brand',
            'label' => 'Brand',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return (optional($entry->brand)->checked) ?
                       '<i class="la la-check text-success" title="Done Without Any Error"></i>'
                       : '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'brand_part_code',
            'label' => 'Brand Part Code/MPN',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional($entry->brand_part_code)->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'short_description',
            'label' => 'Short Description',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->short_description))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'long_description',
            'label' => 'Long Description',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->long_description))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
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
        CRUD::setValidation(IcecatDefinitionRequest::class);
        $this->crud->setCreateContentClass('myDiv col-9');

        CRUD::addField([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        CRUD::addField([
            'name' => 'product_name',
            'type' => 'checkbox',
            'label' => 'Product Name',
        ]);

        CRUD::addField([
            'name' => 'brand',
            'type' => 'checkbox',
            'label' => 'Brand',
        ]);

        CRUD::addField([
            'name' => 'brand_part_code',
            'type' => 'checkbox',
            'label' => 'Brand Part Code/MPN',
        ]);

        CRUD::addField([
            'name' => 'gtin',
            'type' => 'checkbox',
            'label' => 'GTIN',
        ]);

        CRUD::addField([
            'name' => 'brand_logo',
            'type' => 'checkbox',
            'label' => 'Brand Logo',
        ]);

        CRUD::addField([
            'name' => 'features',
            'type' => 'checkbox',
            'label' => 'Features',
        ]);

        CRUD::addField([
            'name' => 'main_image',
            'type' => 'checkbox',
            'label' => 'Main Image',
        ]);

        CRUD::addField([
            'name' => 'thumbnail',
            'type' => 'checkbox',
            'label' => 'Thumbnail',
        ]);

        CRUD::addField([
            'name' => 'gallery',
            'type' => 'checkbox',
            'label' => 'Gallery',
        ]);

        CRUD::addField([
            'name' => 'short_description',
            'type' => 'checkbox',
            'label' => 'Short Description',
        ]);

        CRUD::addField([
            'name' => 'long_description',
            'type' => 'checkbox',
            'label' => 'Long Description',
        ]);

        CRUD::addField([
            'name' => 'document',
            'type' => 'checkbox',
            'label' => 'Document',
        ]);

        /**
         * Fields can be defined using the fluent syntax or array syntax:
         * - CRUD::field('price')->type('number');
         * - CRUD::addField(['name' => 'price', 'type' => 'number']));
         */
    }

    public function store(IcecatDefinitionRequest $request)
    {
        if (! $this->checkThatAtLeastOneFieldIsChecked($request)) {
            throw ValidationException::withMessages(['error' => 'You must check at least one field.']);
        }

        $icecat_definition = new IcecatDefinition;

        $icecat_definition->name = $request->name;
        $icecat_definition->brand = json_encode(['checked' => (bool) $request->brand]);
        $icecat_definition->product_name = json_encode(['checked' => (bool) $request->product_name]);
        $icecat_definition->brand_part_code = json_encode(['checked' => (bool) $request->brand_part_code]);
        $icecat_definition->gtin = json_encode(['checked' => (bool) $request->gtin]);
        $icecat_definition->brand_logo = json_encode(['checked' => (bool) $request->brand_logo]);
        $icecat_definition->features = json_encode(['checked' => (bool) $request->features]);
        $icecat_definition->main_image = json_encode(['checked' => (bool) $request->main_image]);
        $icecat_definition->thumbnail = json_encode(['checked' => (bool) $request->thumbnail]);
        $icecat_definition->gallery = json_encode(['checked' => (bool) $request->gallery]);
        $icecat_definition->short_description = json_encode(['checked' => (bool) $request->short_description]);
        $icecat_definition->long_description = json_encode(['checked' => (bool) $request->long_description]);
        $icecat_definition->document = json_encode(['checked' => (bool) $request->document]);

        $icecat_definition->save();

        $this->crud->setSaveAction($request->save_action);

        return $this->crud->performSaveAction($icecat_definition->getKey());
    }

    public function update(IcecatDefinitionRequest $request)
    {
        if (! $this->checkThatAtLeastOneFieldIsChecked($request)) {
            throw ValidationException::withMessages(['error' => 'You must check at least one field.']);
        }

        $icecat_definition = IcecatDefinition::find($request->id);

        $icecat_definition->name = $request->name;
        $icecat_definition->brand = json_encode(['checked' => (bool) $request->brand]);
        $icecat_definition->brand_part_code = json_encode(['checked' => (bool) $request->brand_part_code]);
        $icecat_definition->product_name = json_encode(['checked' => (bool) $request->product_name]);
        $icecat_definition->gtin = json_encode(['checked' => (bool) $request->gtin]);
        $icecat_definition->brand_logo = json_encode(['checked' => (bool) $request->brand_logo]);
        $icecat_definition->features = json_encode(['checked' => (bool) $request->features]);
        $icecat_definition->main_image = json_encode(['checked' => (bool) $request->main_image]);
        $icecat_definition->thumbnail = json_encode(['checked' => (bool) $request->thumbnail]);
        $icecat_definition->gallery = json_encode(['checked' => (bool) $request->gallery]);
        $icecat_definition->short_description = json_encode(['checked' => (bool) $request->short_description]);
        $icecat_definition->long_description = json_encode(['checked' => (bool) $request->long_description]);
        $icecat_definition->document = json_encode(['checked' => (bool) $request->document]);

        $icecat_definition->update();
        $this->crud->setSaveAction($request->save_action);

        return $this->crud->performSaveAction($icecat_definition->getKey());
    }

    private function checkThatAtLeastOneFieldIsChecked($request)
    {
        $isChecked = false;

        if ($request->brand) {
            $isChecked = true;
        }

        if ($request->brand_part_code) {
            $isChecked = true;
        }

        if ($request->product_name) {
            $isChecked = true;
        }

        if ($request->brand_logo) {
            $isChecked = true;
        }

        if ($request->features) {
            $isChecked = true;
        }

        if ($request->main_image) {
            $isChecked = true;
        }

        if ($request->thumbnail) {
            $isChecked = true;
        }

        if ($request->gallery) {
            $isChecked = true;
        }

        if ($request->short_description) {
            $isChecked = true;
        }

        if ($request->long_description) {
            $isChecked = true;
        }

        if ($request->document) {
            $isChecked = true;
        }

        return $isChecked;
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
        $this->crud->setUpdateContentClass('myDiv col-9');

        CRUD::addField([
            'name' => 'name',
            'type' => 'text',
        ]);

        CRUD::addField([
            'name' => 'product_name',
            'type' => 'checkbox',
            'label' => 'Product Name',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->product_name))->checked,
        ]);

        CRUD::addField([
            'name' => 'brand',
            'type' => 'checkbox',
            'label' => 'Brand',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->brand))->checked,
        ]);

        CRUD::addField([
            'name' => 'brand_part_code',
            'type' => 'checkbox',
            'label' => 'Brand Part Code/MPN',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->brand_part_code))->checked,
        ]);

        CRUD::addField([
            'name' => 'gtin',
            'type' => 'checkbox',
            'label' => 'GTIN',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->gtin))->checked,
        ]);

        CRUD::addField([
            'name' => 'brand_logo',
            'type' => 'checkbox',
            'label' => 'Brand Logo',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->brand_logo))->checked,
        ]);

        CRUD::addField([
            'name' => 'features',
            'type' => 'checkbox',
            'label' => 'Features',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->features))->checked,
        ]);

        CRUD::addField([
            'name' => 'main_image',
            'type' => 'checkbox',
            'label' => 'Main Image',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->main_image))->checked,
        ]);

        CRUD::addField([
            'name' => 'thumbnail',
            'type' => 'checkbox',
            'label' => 'Thumbnail',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->thumbnail))->checked,
        ]);

        CRUD::addField([
            'name' => 'gallery',
            'type' => 'checkbox',
            'label' => 'Gallery',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->gallery))->checked,
        ]);

        CRUD::addField([
            'name' => 'short_description',
            'type' => 'checkbox',
            'label' => 'Short Description',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->short_description))->checked,
        ]);

        CRUD::addField([
            'name' => 'long_description',
            'type' => 'checkbox',
            'label' => 'Long Description',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->long_description))->checked,
        ]);

        CRUD::addField([
            'name' => 'document',
            'type' => 'checkbox',
            'label' => 'Document',
            'value' => optional(json_decode($this->crud->getCurrentEntry()->document))->checked,
        ]);
    }

    protected function setupShowOperation(): void
    {
        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Name',
        ]);

        CRUD::addColumn([
            'name' => 'product_name',
            'label' => 'Product Name',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->product_name))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'brand',
            'label' => 'Brand',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->brand))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'brand_part_code',
            'label' => 'Brand Part Code/MPN',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->brand_part_code))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'short_description',
            'label' => 'Short Description',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->short_description))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'long_description',
            'label' => 'Long Description',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->long_description))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'gtin',
            'label' => 'GTIN',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->gtin))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'brand_logo',
            'label' => 'Brand Logo',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->brand_logo))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'features',
            'label' => 'Features',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->features))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'main_image',
            'label' => 'Main Image',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->main_image))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'thumbnail',
            'label' => 'Thumbnail',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->thumbnail))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'gallery',
            'label' => 'Gallery',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->gallery))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'document',
            'label' => 'Document',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if (optional(json_decode($entry->document))->checked) {
                    return '<i class="la la-check text-success" title="Done Without Any Error"></i>';
                }

                return '<i class="la la-check text-danger" title="Done Without Any Error"></i>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'updated_at',
            'label' => 'Updated At',
            'type' => 'text',
        ]);
    }
}

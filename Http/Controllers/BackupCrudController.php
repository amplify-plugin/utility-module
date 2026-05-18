<?php

namespace Amplify\System\Utility\Http\Controllers;

use Amplify\System\Abstracts\BackpackCustomCrudController;
use Amplify\System\Utility\Jobs\DbBackupQueueJob;
use Amplify\System\Utility\Models\ApiLog;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Prologue\Alerts\Facades\Alert;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class WarehouseCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class BackupCrudController extends BackpackCustomCrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(ApiLog::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/backup');
        CRUD::setEntityNameStrings('backup', 'backups');
    }

    protected function setupCustomRoutes($segment, $routeName, $controller)
    {
        Route::get("{$segment}/create", [
            'as' => "{$routeName}.create",
            'uses' => "{$controller}@create",
            'operation' => 'create',
        ]);

        Route::get("{$segment}/{filename}/download", [
            'as' => "{$routeName}.download",
            'uses' => "{$controller}@download",
            'operation' => 'download',
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
        $this->crud->addButton('line', 'download', 'view', 'backend::buttons.backup_download', 'beginning');

        CRUD::addColumns([
            [
                'name' => 'name',
                'label' => 'File',
            ],
            [
                'name' => 'size',
                'label' => 'Size',
                'type' => 'text',
                'orderable' => false,
            ],
            [
                'name' => 'modified',
                'label' => 'Created',
                'type' => 'datetime',
                'orderable' => false,
            ],
        ]);
    }

    /**
     * The search function that is called by the data table.
     *
     * @return array JSON Array of cells in HTML form.
     */
    public function search()
    {
        $this->crud->hasAccessOrFail('list');

        $disk = Storage::disk('backups');

        $files = $disk->allFiles();

        $entries = collect();

        foreach ($files as $index => $f) {
            if (str_ends_with($f, '.zip') && $disk->exists($f)) {
                $entries->push(new class($index, $f, $disk) {
                    public $index;
                    public string $id;
                    public string $name;
                    public string $size;
                    public $modified;

                    public function __construct($pos, $f, $disk)
                    {
                        $this->id = base64_encode(basename($f));
                        $this->index = $pos + 1;
                        $this->name = ucfirst(basename($f));
                        $this->size = Number::fileSize($disk->size($f));
                        $this->modified = Carbon::createFromTimestamp($disk->lastModified($f));
                    }

                    public function getKey()
                    {
                        return $this->id;
                    }
                });
            }
        }

        $entries = $entries->sortByDesc('modified');

        $totalEntryCount = $entries->count();

        $search = request()->input('search');
        $start = request()->input('start', 0);
        $length = request()->input('length', 10);

        // if a search term was present
        if ($search && $search['value'] ?? false) {
            $entries = $entries->filter(function ($entry) use ($search) {
                return str_contains($entry->name, $search['value']);
            });
        }

        // start the results according to the datatables pagination
        if ($start) {
            $entries = $entries->skip($start);
        }
        // limit the number of results according to the datatables pagination
        if ($length) {
            $entries = $entries->take($length);
        }

        // if show entry count is disabled we use the "simplePagination" technique to move between pages.
        if ($this->crud->getOperationSetting('showEntryCount')) {
            $filteredEntryCount = $this->crud->getFilteredQueryCount() ?? $totalEntryCount;
        } else {
            $totalEntryCount = $length;
            $filteredEntryCount = $entries->count() < $length ? 0 : $length + $start + 1;
        }

        // store the totalEntryCount in CrudPanel so that multiple blade files can access it
        $this->crud->setOperationSetting('totalEntryCount', $totalEntryCount);

        return $this->crud->getEntriesAsJsonForDatatables($entries->toArray(), $totalEntryCount, $filteredEntryCount, $start);
    }

    public function create(): RedirectResponse
    {
        $this->crud->hasAccessOrFail('create');

        try {

            if (!app()->isProduction()) {

                Alert::error('<strong>Backup Process Failed</strong><br>The Backup process can only be run in production environment')->flash();

                return redirect()->back();
            }

            DbBackupQueueJob::dispatch();

            Alert::success('<strong>Backup Process Started</strong><br>The backup file will be available for download once the process is complete')->flash();

        } catch (\Exception $e) {

            Log::error($e);

            Alert::error('Failed to start backup process. Please try again.')->flash();
        }

        return redirect()->back();

    }

    /**
     * Downloads a backup zip file.
     *
     * @return StreamedResponse|RedirectResponse
     */
    public function download($filename)
    {
        $this->crud->hasAccessOrFail('download');

        $filename = base64_decode($filename);

        $disk = Storage::disk('backups');

        if (!$disk->exists($filename)) {
            abort(404, trans('backpack::backup.backup_doesnt_exist'));
        }

        // S3 or S3-compatible disks
        if (in_array($disk->getConfig()['driver'], ['s3'])) {

            return redirect()->away(str_replace('\\', '/', $disk->url($filename)));
        }

        // Local disk
        return response()->streamDownload(function () use ($disk, $filename) {

            $stream = $disk->readStream($filename);

            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

        }, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        $filename = base64_decode($id);

        $disk = Storage::disk('backups');

        if (!$disk->exists($filename)) {
            abort(404, trans('backpack::backup.backup_doesnt_exist'));
        }

        return $disk->delete($filename);
    }
}

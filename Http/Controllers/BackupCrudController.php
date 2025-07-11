<?php

namespace Amplify\System\Utility\Http\Controllers;

use App\Abstracts\BackpackCustomCrudController;
use App\Traits\ZipperTrait;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
    use ZipperTrait;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setRoute(config('backpack.base.route_prefix').'/backup');
        CRUD::setEntityNameStrings('backup', 'backups');
    }

    protected function setupCustomRoutes($segment, $routeName, $controller)
    {
        Route::post($segment.'/create', [
            'as' => $routeName.'.create',
            'uses' => $controller.'@create',
        ]);
        Route::get($segment.'/download/{filename?}', [
            'as' => $routeName.'.download',
            'uses' => $controller.'@download',
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
        $this->data['backups'] = [];

        $this->crud->setHeading('Backups');

        foreach (config('backup.backup.destination.disks') as $disk_name) {
            $disk = Storage::disk($disk_name);
            $can_download = backpack_user()->can('backup.download');
            $files = $disk->allFiles();

            // make an array of backup files, with their filesize and creation date
            foreach ($files as $k => $f) {
                // only take the zip files into account
                if (substr($f, -4) == '.zip' && $disk->exists($f)) {
                    $this->data['backups'][] = [
                        'file_path' => $f,
                        'file_name' => basename($f),
                        'file_size' => $disk->size($f),
                        'last_modified' => $disk->lastModified($f),
                        'disk' => $disk_name,
                        'download' => $can_download,
                    ];
                }
            }
        }

        $this->data['backups'] = array_reverse($this->data['backups']);

        $this->crud->setSubheading('Showing all entries on backups disks');

        $this->crud->setListView('crud::pages.backup.list');
    }

    public function create(Request $request): \Illuminate\Http\Response|string
    {
        $message = 'success';
        $objects = $request->input('objects');

        if (empty($objects)) {
            return Response::make('Please select at least one object.', 500);
        }

        try {
            ini_set('max_execution_time', 600);

            Log::info('Backpack\BackupManager -- Called backup:run from admin interface');

            if (in_array('full_backup', $objects)) {
                Artisan::call('backup:run --disable-notifications');
            } elseif (in_array('database', $objects)) {
                Artisan::call('backup:run --only-db --disable-notifications');
            }

            $output = Artisan::output();
            if (strpos($output, 'Backup failed because')) {
                preg_match('/Backup failed because(.*?)$/ms', $output, $match);
                $message = "Backpack\BackupManager -- backup process failed because ";
                $message .= isset($match[1]) ? $match[1] : '';
                Log::error($message.PHP_EOL.$output);
            } else {
                Log::info("Backpack\BackupManager -- backup process has started");
            }

            if (in_array('storage', $objects) && ! in_array('full_backup', $objects)) {
                $folders = ['storage', 'uploads'];

                foreach ($folders as $folder) {
                    $folderPath = public_path($folder);
                    $zipFileName = time().'-'.$folder.'-backup-'.date('Y-m-d').'.zip';
                    $zipResult = $this->takeStorageBackup($folderPath, $zipFileName);

                    if ($zipResult != 'success') {
                        $message = $zipResult;
                        Log::error('BackUp of '.$folder.' failed. Error:'.$message);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error($e);

            return Response::make($e->getMessage(), 500);
        }

        return $message;
    }

    /**
     * Downloads a backup zip file.
     *
     * @return BinaryFileResponse|StreamedResponse|void
     */
    public function download(Request $request)
    {
        $disk_name = $request->input('disk');
        $disk = Storage::disk($disk_name);
        $file_name = $request->input('path');
        $can_download = backpack_user()->can('backup.download');

        if ($can_download) {
            if ($disk->exists($file_name)) {
                if (method_exists($disk->getAdapter(), 'getPathPrefix')) {
                    $storage_path = $disk->getAdapter()->getPathPrefix();

                    return response()->download($storage_path.$file_name);
                } else {
                    return $disk->download($file_name);
                }
            } else {
                abort(404, trans('backpack::backup.backup_doesnt_exist'));
            }
        } else {
            abort(403);
        }
    }

    /**
     * Deletes a backup file.
     */
    public function destroy($file_name, Request $request): string
    {
        $diskName = $request->input('disk', 'backups');

        if (! in_array($diskName, config('backup.backup.destination.disks'))) {
            abort(500, trans('backpack::backup.unknown_disk'));
        }

        $disk = Storage::disk($diskName);

        if ($disk->exists(config('backup.backup.name').'/'.$file_name)) {
            $disk->delete(config('backup.backup.name').'/'.$file_name);

            return 'success';
        } else {
            abort(404, trans('backpack::backup.backup_doesnt_exist'));
        }
    }
}

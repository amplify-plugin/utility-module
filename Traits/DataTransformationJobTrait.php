<?php

namespace Amplify\System\Utility\Traits;

use Amplify\System\Utility\Http\Controllers\ImportJobCrudController;
use Amplify\System\Utility\Models\DataTransformation;
use Amplify\System\Utility\Models\DataTransformationError;
use Amplify\System\Utility\Models\ImportDefinition;
use Amplify\System\Utility\Models\ImportJobHistory;
use App\Exports\ImportJobExport;
use App\Helpers\DBHelper;
use App\Imports\ImportJobImport;
use App\Jobs\ParentImportJob;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as MaatwebsiteExcel;
use Maatwebsite\Excel\Facades\Excel;
use Prologue\Alerts\Facades\Alert;

trait DataTransformationJobTrait
{
    public array $retryImportJobData;

    public int $importJobId;

    public int $delay = 0;

    public Builder $importJobInstance;

    public Builder $importErrorInstance;

    public Builder $importJobHistoryInstance;

    public SupportCollection $uuids;

    protected function setupCustomJobsRoutes($segment, $routeName, $controller)
    {
        // /admin/import-job/retry/failed-job
        Route::post($segment.'/retry/failed-job', [
            'as' => $routeName.'.retry.failedJob',
            'uses' => $controller.'@retryFailedJob',
            'operation' => 'retryFailedJob',
        ]);

        // /admin/import-job/update/failed-job
        Route::post($segment.'/update/failed-job', [
            'as' => $routeName.'.update.failedJob',
            'uses' => $controller.'@updateFailedJob',
            'operation' => 'updateFailedJob',
        ]);

        // /admin/import-job/retry-import-job/{id}
        Route::get($segment.'/retry-import-job/{id}', [
            'as' => $routeName.'.retry.import.job',
            'uses' => $controller.'@retryImportJob',
            'operation' => 'retryImportJob',
        ]);
    }

    public function retryFailedJob(Request $request): JsonResponse
    {
        $uuids = (array) ($request->uuid ?? $request->uuids ?? '');
        $id = $request->id ?? null;

        $this->updateImportJob($id, $uuids);

        // Retry job(s)
        Artisan::call('queue:retry', ['id' => $uuids]);

        $uuids = implode(',', $uuids);
        $message = "The failed job<small>(s)</small>
                    <br/>
                    <strong>[$uuids]</strong>
                    <br/>
                    has been ".
                   ($request->retry
                       ? ' updated and '
                       : '')
                   .' pushed back onto the queue!';

        return response()->json(compact('message', 'uuids'));
    }

    public function updateFailedJob(Request $request): ?JsonResponse
    {
        $uuids = (array) ($request->uuid ?? $request->uuids ?? '');
        $updateImportData = $request->updateImportData;

        DBHelper::updateJobPayloadForDataTransformation($uuids, $updateImportData, $request->id);

        $uuids = implode(',', $uuids);
        $message = "The failed job<small>(s)</small>
                    <br/>
                    <strong>[$uuids]</strong>
                    <br/>
                    has been updated!";

        $response = null;

        if ($request->retry) {
            $response = $this->retryFailedJob($request);
        }

        return $request->retry
            ? $response
            : response()->json(compact('message', 'uuids'));
    }

    private function updateImportJob($id, $uuids)
    {
        // Decrease failed count from ImportJob
        $importJob = DataTransformation::query()->findOrFail($id);
        $importJob->failed_count = $importJob->failed_count - count($uuids);
        $importJob->save();

        // Delete existing errors
        DataTransformationError::query()->whereIn('uuid', $uuids)->delete();
    }

    public function fetchImportDefinition(): JsonResponse
    {
        $importDefinition = ImportDefinition::query()
            ->where('name', 'like', '%'.request()->q.'%')
            /* ->orWhere('description', 'like', '%' . request()->q . '%') */
            ->get();

        return response()->json($importDefinition);
    }

    public function fetchImportDefinitions(): JsonResponse
    {
        return response()->json(ImportDefinition::all());
    }

    public function handleUploadFile(): JsonResponse
    {
        $request = request();
        $file = $request->file('file');
        $model_name = strtolower($request->model_name);
        $file_path = 'import_files/jobs/'.$model_name;
        self::makeDir($file_path);

        $path = Storage::disk('public')->path("{$file_path}/{$file->getClientOriginalName()}");

        File::append($path, $file->get());
        $name = $file->getClientOriginalName();

        $fullPath = '';
        $row_count = 0;

        if ($request->has('is_last') && $request->boolean('is_last')) {
            $name = time().'-'.basename($path, '.part');
            $fullPath = "{$file_path}/{$name}";

            File::move($path, Storage::disk('public')->path($fullPath));
            $row_count = getFileDataCount(
                new ImportJobImport,
                $fullPath,
                MaatwebsiteExcel::CSV,
                $request->is_column_heading ?? true,
                'public'
            );
        }

        return response()->json(['path' => $fullPath, 'row_count' => $row_count]);
    }

    /**
     * @request [chunk_size, is_column_heading, row_count, file_path, import_definition_id]
     */
    public function fetchMakePieces()
    {
        $importDefinition = ImportDefinition::query()->findOrFail(request()->import_definition_id);

        $fileData = getFileData(
            new ImportJobImport,
            request()->file_path,
            MaatwebsiteExcel::CSV,
            'public'
        );

        if ($importDefinition->is_column_heading) {
            $heading = $fileData->first();
            $fileData->shift();
        } else {
            $heading = [];
        }

        $chunkCount = ceil(
            ($chunkCount = request()->row_count / request()->chunk_size) < 1
                ? 1
                : $chunkCount
        );

        if ($chunkCount > 1) {
            $newDirName = Str::lower(Str::slug(getFileName(request()->file_path)));
            $fullDirName = removeFileNameFromPath(request()->file_path).$newDirName;
            self::makeDir($fullDirName);

            foreach ($fileData->chunk(request()->chunk_size) as $key => $chunk) {
                if ($importDefinition->is_column_heading) {
                    $chunk->prepend($heading);
                }

                $startCount = $key * request()->chunk_size + 1;
                $endCount = $startCount + request()->chunk_size - 1;

                $path = "$fullDirName/$startCount-$endCount.".Str::lower($importDefinition->file_type);

                Excel::store(
                    new ImportJobExport($chunk),
                    $path,
                    'public',
                    MaatwebsiteExcel::CSV
                );
            }
        }

        return response()->json(compact('chunkCount'));
    }

    private static function makeDir($dir)
    {
        if (! Storage::exists($dir)) {
            Storage::disk('public')->makeDirectory($dir);
        }
    }

    public function setImportJobId(int $importJobId): ImportJobCrudController
    {
        $this->importJobId = $importJobId;

        return $this;
    }

    public function getRetryImportJobData($isSaveAndRetry): ImportJobCrudController
    {
        // Preparing data for Import Job
        $this->importJobInstance = DataTransformation::query();
        $importJob = $this->importJobInstance->find($this->importJobId);

        if (! $isSaveAndRetry) {
            $this->delay = Carbon::now()->diffInSeconds($importJob->schedule_time);
        }

        if ($importJob->row_count > $importJob->chunk_size) {
            $filePath = explode('/', $importJob->file_path);
            $filePath[count($filePath) - 1] = Str::slug(removeExtension($filePath[count($filePath) - 1]));
            $fileDirectory = strtolower(implode('/', $filePath));
            $fileExt = getFileExtension($importJob->file_path);
            $getAllFilesFromDirectory = getFilesFromStorage($fileDirectory, $fileExt);
            foreach ($getAllFilesFromDirectory as $file) {
                $this->retryImportJobData[] = [
                    'import_job_id' => $importJob->id,
                    'import_definition_id' => $importJob->import_definition_id,
                    'user_id' => $importJob->user_id,
                    'locale' => $importJob->locale,
                    'file_path' => $file,
                ];
            }
        } else {
            $this->retryImportJobData[] = [
                'import_job_id' => $importJob->id,
                'import_definition_id' => $importJob->import_definition_id,
                'user_id' => $importJob->user_id,
                'locale' => $importJob->locale,
                'file_path' => $importJob->file_path,
            ];
        }

        return $this;
    }

    public function getUuids(): ImportJobCrudController
    {
        // Getting UUIDs
        $this->importErrorInstance = DataTransformationError::query();
        $this->uuids = $this->importErrorInstance
            ->where('import_job_id', $this->importJobId)
            ->pluck('uuid');

        return $this;
    }

    public function cleanupDB()
    {
        // Deleting all errors by import_job_id
        $this->importErrorInstance->where('import_job_id', $this->importJobId)->delete();
        $this->importJobHistoryInstance = ImportJobHistory::query();
        $this->importJobHistoryInstance->where('import_job_id', $this->importJobId)->delete();

        // Deleting all errors by uuid from failed_jobs table
        DB::table('failed_jobs')->whereIn('uuid', $this->uuids)->delete();

        // Resetting Import Job
        $this->importJobInstance->where('id', $this->importJobId)->update([
            'success_count' => 0,
            'failed_count' => 0,
            'status' => 'pending',
        ]);
    }

    protected function retryImportJob($id, int $delay = 0, bool $isSaveAndRetry = false): RedirectResponse
    {
        $this->setImportJobId($id)->getRetryImportJobData($isSaveAndRetry)->getUuids()->cleanupDB();

        // Get delay
        $this->delay = $isSaveAndRetry
            ? $delay
            : $this->delay;

        // Dispatching Import Job
        if (! empty($this->retryImportJobData)) {
            foreach ($this->retryImportJobData as $data) {
                ParentImportJob::dispatch($data)->delay($this->delay);
            }
            Alert::add('success', "Import job (ID: $this->importJobId) successfully pushed back onto the queue!")->flash();
        } else {
            Alert::add('error', "Import job (ID: $this->importJobId) failed to pushed back onto the queue!")->flash();
        }

        return redirect()->back();
    }
}

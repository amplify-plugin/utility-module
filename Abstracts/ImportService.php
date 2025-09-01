<?php

namespace Amplify\System\Utility\Abstracts;

use Amplify\System\Imports\ImportJobImport;
use Amplify\System\Jobs\AttributeServiceJob;
use Amplify\System\Jobs\CategoryServiceJob;
use Amplify\System\Jobs\ProductServiceJob;
use Amplify\System\Utility\Models\ImportDefinition;
use Amplify\System\Utility\Models\ImportJob;
use Maatwebsite\Excel\Excel;

/**
 * @property $request
 * @property ImportDefinition $importDefinition
 * @property mixed $column_mapping
 * @property mixed $modelInstance
 *
 * @const array DISPLAY_NAMES
 */
abstract class ImportService
{
    /**
     * @var mixed
     */
    protected $modelInstance;

    protected string $imageSeparator = ',';

    protected int $totalRow = 0;

    protected int $delay = 0;

    protected int $importJobId;

    protected int $userId;

    protected string $locale;

    protected array $request;

    protected array $column_mapping;

    protected object $importDefinition;

    protected string $jobFullName;

    protected string $model;

    protected ?array $fileData;

    const DISPLAY_NAMES = [
        CategoryServiceJob::class,
        AttributeServiceJob::class,
        ProductServiceJob::class,
    ];

    public function __construct(ImportDefinition $importDefinition, $request)
    {
        echo '## ImportService :: __construct() ##', PHP_EOL, PHP_EOL;

        $this->request = $request;
        $this->importDefinition = $importDefinition;
        $this->column_mapping = json_decode($this->importDefinition->column_mapping);

        if (! in_array($this->importDefinition->import_type, ['ContactPermissions'])) {
            $this->model = "App\Models\\".$this->importDefinition->import_type;
            $this->modelInstance = new $this->model;
        }

        $this->fileData = $this->readFile(
            new ImportJobImport,
            $this->request['file_path'],
            Excel::CSV,
            $this->importDefinition->is_column_heading,
            'public',
        );
    }

    /**
     * Read File.
     */
    public function readFile($toCollection, $filePath, $readerType, bool $hasColumnHeading = false, string $disc = 'local', $take = null): array
    {
        $response = readFileFromLocal($toCollection, $filePath, $readerType, $hasColumnHeading, $disc, $take);
        $this->totalRow = $response['totalRow'];

        if (! $take) {
            $importJob = ImportJob::query()->find($this->request['import_job_id'] ?? null);
            /*$importJob->row_count = $this->totalRow + 1; // This added one this the parent job itself
            $importJob->save();*/
        }

        return $response;
    }

    public function setDelay(int $delay): void
    {
        $this->delay = $delay;
    }

    /**
     * Manage Import Job History.
     */
    protected function manageImportJobHistory()
    {
        /*$job         = DB::table('jobs')->orderBy('id', 'desc')->first();
        $payload     = json_decode($job->payload ?? null);
        $displayName = $payload->displayName;

        if (($uuid = ($payload->uuid ?? false)) && $displayName === $this->jobFullName) {
            manageImportJobHistory($uuid, $this->importJobId, 'create', 'pending');
        }*/
    }

    /**
     * Process Parent Job Which Will Create Other Jobs To Insert Data
     *
     * @return void
     */
    abstract protected function process();
}

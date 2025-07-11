<?php

namespace Amplify\System\Utility\Services\Import;

use Amplify\System\Utility\Abstracts\ImportService;
use Amplify\System\Utility\Models\ImportDefinition;
use App\Jobs\CategoryServiceJob;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * @property $request
 * @property ImportDefinition $importDefinition
 * @property mixed $column_mapping
 * @property mixed $modelInstance
 */
class CategoryService extends ImportService implements ShouldQueue
{
    use Dispatchable;

    public function __construct(ImportDefinition $importDefinition, $request)
    {
        $this->importJobId = $request['import_job_id'];
        $this->userId = $request['user_id'];
        $this->locale = $request['locale'];
        $this->jobFullName = CategoryServiceJob::class;

        parent::__construct($importDefinition, $request);
    }

    public function process()
    {
        echo '## CategoryService :: process() ##', PHP_EOL, PHP_EOL;

        $csvData = collect($this->fileData['csvArray'] ?? []);

        $csvData->each(function ($aCsv) {
            $data = [
                'aCsv' => $aCsv,
                'column_mapping' => $this->column_mapping,
                'importJobId' => $this->importJobId,
                'userId' => $this->userId,
                'locale' => $this->locale,
                'importDefinition' => $this->importDefinition,
            ];

            CategoryServiceJob::dispatch($data)->delay(Carbon::now()->addSeconds($this->delay));

            $this->manageImportJobHistory();
        });
    }

    protected function getMappingProcessed($aCsv)
    {
        // TODO: save category
    }
}

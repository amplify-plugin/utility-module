<?php

namespace Amplify\System\Utility\Services\Import;

use Amplify\System\Utility\Abstracts\ImportService;
use Amplify\System\Utility\Models\ImportDefinition;
use App\Jobs\AttributeProductServiceJob;
use Carbon\Carbon;

/**
 * @property $request
 * @property ImportDefinition $importDefinition
 * @property mixed $column_mapping
 * @property mixed $modelInstance
 */
class AttributeProductService extends ImportService
{
    /**
     * @var mixed
     */
    protected $attribute_value;

    public function __construct(ImportDefinition $importDefinition, $request)
    {
        $this->importJobId = $request['import_job_id'];
        $this->userId = $request['user_id'];
        $this->locale = $request['locale'];
        $this->jobFullName = AttributeProductServiceJob::class;

        parent::__construct($importDefinition, $request);
    }

    /**
     * @return void
     */
    public function process()
    {
        echo '## AttributeService :: process() ##', PHP_EOL, PHP_EOL;

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

            AttributeProductServiceJob::dispatch($data)->delay(Carbon::now()->addSeconds($this->delay));

            $this->manageImportJobHistory();
        });
    }

    protected function getMappingProcessed($aCsv)
    {
        //
    }
}

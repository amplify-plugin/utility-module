<?php

namespace Amplify\System\Utility\Services\Import;

use Amplify\System\Backend\Models\AttributeProduct;
use Amplify\System\Backend\Models\Product;
use Amplify\System\Backend\Models\ProductImage;
use Amplify\System\Jobs\ProductServiceJob;
use Amplify\System\Utility\Abstracts\ImportService;
use Amplify\System\Utility\Models\ImportDefinition;
use Carbon\Carbon;

/**
 * @property $request
 * @property ImportDefinition $importDefinition
 * @property mixed $column_mapping
 * @property mixed $modelInstance
 */
class ProductService extends ImportService
{
    private Product $product;

    protected array $multipleValuesDataOfAttribute = [];

    private ProductImage $productImage;

    private AttributeProduct $attributeProduct;

    public function __construct(ImportDefinition $importDefinition, $request)
    {
        echo '## ProductService :: __construct() ##', PHP_EOL, PHP_EOL;

        $this->importJobId = $request['import_job_id'];
        $this->userId = $request['user_id'];
        $this->locale = $request['locale'];
        $this->jobFullName = ProductServiceJob::class;

        parent::__construct($importDefinition, $request);
    }

    /**
     * @return void
     */
    public function process()
    {
        echo '## ProductService :: process() ##', PHP_EOL, PHP_EOL;

        $csvData = collect($this->fileData['csvArray'] ?? []);

        $csvData->each(function ($aCsv) {
            $data = [
                'aCsv' => $aCsv,
                'column_mapping' => $this->column_mapping,
                'imageSeparator' => $this->imageSeparator,
                'importJobId' => $this->importJobId,
                'userId' => $this->userId,
                'locale' => $this->locale,
                'importDefinition' => $this->importDefinition,
            ];

            ProductServiceJob::dispatch($data)->delay(Carbon::now()->addSeconds($this->delay));

            $this->manageImportJobHistory();
        });
    }

    protected function getMappingProcessed($aCsv)
    {
        // TODO: getMappingProcessed in ProductService
    }
}

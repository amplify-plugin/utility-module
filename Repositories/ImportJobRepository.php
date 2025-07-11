<?php

namespace Amplify\System\Utility\Repositories;

use Amplify\System\Utility\Models\ImportDefinition;
use Amplify\System\Utility\Repositories\Interfaces\ImportJobInterface;

class ImportJobRepository implements ImportJobInterface
{
    /**
     * @param  array  $request
     *
     * @throws \ErrorException
     */
    public function processImportJob($request): void
    {
        echo PHP_EOL.'## ImportJobRepository :: processImportJob() ##'.PHP_EOL;

        $importDefinition = ImportDefinition::query()->findOrFail($request['import_definition_id']);
        $model = $importDefinition->import_type;
        $serviceName = "Amplify\System\Utility\Services\Import\\{$model}Service";

        if (class_exists($serviceName)) {
            (new $serviceName($importDefinition, $request))->process();
        } else {
            echo PHP_EOL."## Class `{$serviceName}` doesn't exists on system.".PHP_EOL;
        }
    }
}

<?php
// Run with: php tests/ProductImportJobTest.php PATH_TO_APP_VENDOR_AUTOLOAD
require $argv[1];
$packagePath = $argv[2] ?? dirname(__DIR__);
require_once $packagePath.'/Listeners/ImportedProductFlags.php';
require_once $packagePath.'/Jobs/ProductImportJob.php';

use Amplify\System\Backend\Enums\ProductAvailabilityEnum;
use Amplify\System\Backend\Models\Product;
use Amplify\System\Utility\Jobs\ProductImportJob;
use Amplify\System\Utility\Listeners\ImportedProductFlags;
use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$app = new Application(dirname($argv[1], 2));
$app->instance('config', new Repository(['app' => ['locale' => 'en'], 'sluggable' => ['onUpdate' => false]]));
$app->instance('request', Request::create('/', 'GET'));
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$events = new Dispatcher($app);
$app->instance('events', $events);
Product::setEventDispatcher($events);
Product::observe(ImportedProductFlags::class);

class ImportProbe extends ProductImportJob
{
    public function __construct() {}
    public function apply(array $fields, array $values): void
    {
        $this->column_mapping = array_map(fn ($field) => (object) [
            'map_to' => 'Field', 'field_or_attribute_name' => $field,
            'column_name' => $field,
        ], $fields);
        $this->aCsv = $values;
        $this->handleColumnMapping();
    }
    public function failureData(): void { $this->handleColumnMapping(true); }
    public function attemptSave(): void { $this->saveDataToDatabase($this->aCsv); }
}

$checks = 0;
function check($condition, $message): void
{
    global $checks;
    if (! $condition) { throw new RuntimeException($message); }
    $checks++;
}
function probe(array $flags = [], bool $update = true): ImportProbe
{
    $job = new ImportProbe;
    $job->product = new Product;
    $job->product->user_id = 1;
    $job->product->product_type = 'normal';
    $job->product->product_slug = 'import-test';
    $job->product->flags = $flags;
    $job->is_updating = $update;
    return $job;
}
function rejects(callable $action, string $text): void
{
    try { $action(); } catch (ErrorException $e) {
        check(str_contains($e->getMessage(), $text), 'Wrong validation error');
        return;
    }
    throw new RuntimeException('Expected validation error');
}

foreach (array_column(ProductAvailabilityEnum::cases(), 'value') as $availability) {
    foreach (['D', 'Y'] as $price) {
        $job = probe(['ship_restriction' => 'keep']);
        $job->apply(['availability_display', 'price_display'], [strtolower($availability).' ', ' '.$price]);
        check($job->product->flags === ['ship_restriction' => 'keep', 'availability' => $availability, 'price' => $price], 'Flags not merged');
        check(! array_key_exists('availability_display', $job->product->getAttributes()), 'Virtual field leaked into DB attributes');
        check(! array_key_exists('price_display', $job->product->getAttributes()), 'Virtual field leaked into DB attributes');
    }
}
$job = probe(['availability' => 'R', 'price' => 'Y']);
$job->apply(['availability_display', 'price_display'], [' ', null]);
check($job->product->flags === ['availability' => 'R', 'price' => 'Y'], 'Blank values changed flags');
$job = probe([], false);
$job->apply(['availability_display', 'price_display'], ['', null]);
check($job->product->flags === [], 'Blank values assigned defaults on a new product');
foreach (['availability_display', 'price_display'] as $field) {
    rejects(fn () => probe()->apply([$field], ['invalid']), 'Invalid');
    rejects(fn () => probe()->apply([$field, $field], ['A', 'D']), 'Duplicate');
    rejects(fn () => probe()->apply(['flags', $field], ['{}', 'A']), 'either flags');
    rejects(fn () => probe()->apply([$field, 'flags'], ['A', '{}']), 'either flags');
}
$job = probe();
$job->apply(['upc_number'], ['123456789012']);
check($job->product->upc_number === '123456789012', 'Ordinary field mapping broken');
rejects(fn () => probe()->apply(['upc_number'], ['123']), 'UPC Number');
$job->apply(['availability_display'], ['A']);
$job->failureData();
check($job->thisImportData['availability_display'] === 'A', 'Failure reporting broken');

// Exercise the real Product creating/updating callbacks, without touching a database.
$fire = new ReflectionMethod(Product::class, 'fireModelEvent');
foreach (['creating', 'updating'] as $event) {
    $job = probe(['ship_restriction' => 'keep'], $event === 'updating');
    $job->apply(['availability_display', 'price_display'], ['S', 'Y']);
    $job->product->flags = [...$job->product->flags, 'transformed' => 'keep'];
    $app->instance('request', Request::create('/', $event === 'updating' ? 'PUT' : 'GET'));
    $fire->invoke($job->product, 'saving');
    $fire->invoke($job->product, $event);
    check($job->product->flags === ['ship_restriction' => 'keep', 'availability' => 'S', 'price' => 'Y', 'transformed' => 'keep'], 'Model callback lost imported flags or transformation');
    ImportedProductFlags::forget($job->product);
    $job->product->flags = [];
    (new ImportedProductFlags)->creating($job->product);
    check($job->product->flags === [], 'Completed import leaked observer state');
}
$app->instance('request', Request::create('/', 'GET'));
$ordinary = new Product;
$ordinary->user_id = 1;
$ordinary->flags = ['price' => 'Y'];
$fire->invoke($ordinary, 'creating');
check($ordinary->flags === [], 'Observer changed a non-imported product');
// Invalid second value must not leave the product tracked after a failed save.
$job = probe(['price' => 'D']);
rejects(fn () => $job->apply(['availability_display', 'price_display'], ['R', 'invalid']), 'Invalid');
rejects(fn () => $job->attemptSave(), 'Invalid');
$job->product->flags = [];
(new ImportedProductFlags)->creating($job->product);
check($job->product->flags === [], 'Failed import leaked observer state');

// Corrupt historical flags are rejected instead of being silently replaced.
$job = probe();
$job->product->flags = 'invalid';
rejects(fn () => $job->apply(['price_display'], ['Y']), 'JSON object');

// One import must not alter another product instance.
$tracked = probe(['ship_restriction' => 'keep']);
$tracked->apply(['price_display'], ['Y']);
$untracked = new Product;
$untracked->user_id = 1;
$untracked->flags = ['price' => 'Y'];
$fire->invoke($untracked, 'creating');
check($untracked->flags === [], 'Tracked product affected a different product');
ImportedProductFlags::forget($tracked->product);

// Check the dropdown source without querying a database.
require_once dirname($argv[1]).'/backpack/crud/src/helpers.php';
require_once $packagePath.'/Http/Controllers/ImportDefinitionCrudController.php';
$controller = new class extends Amplify\System\Utility\Http\Controllers\ImportDefinitionCrudController {
    public function __construct() {}
    public function getDBColumns($type): array { return [['name'=>'id','type'=>'bigint','is_required'=>true]]; }
};
$names = array_column($controller->generateProductColumns('Product'), 'name');
check(in_array('availability_display', $names, true) && in_array('price_display', $names, true) && in_array('id', $names, true), 'Dropdown missing display or ordinary fields');

echo "PASS: {$checks} checks; no database writes.\n";


<?php

namespace Amplify\System\Utility\Traits;

use Amplify\System\Jobs\ExecuteScriptJob;
use Amplify\System\Jobs\ProcessIcecaProductsInformationJob;
use Amplify\System\Utility\Models\IcecatDefinition;
use Amplify\System\Utility\Models\IcecatTransformation;
use Amplify\System\Utility\Services\IcecatTransformation\ExecuteScriptService;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

trait IcecatTransformationTrait
{
    protected function setupCustomRoutes($segment, $routeName, $controller)
    {
        // /admin/test/script/{icecatTransformation}
        Route::get($segment.'/icecat-test/script/{icecatTransformation}', [
            'as' => $routeName.'.test.script',
            'uses' => $controller.'@loadTestBlade',
            'operation' => 'loadTestBlade',
        ]);

        // /admin/test/script/{icecatTransformation}
        Route::get($segment.'/icecat-run/script/{icecatTransformation}', [
            'as' => $routeName.'.run.script',
            'uses' => $controller.'@loadRunBlade',
            'operation' => 'loadRunBlade',
        ]);
    }

    // function to show the test blade
    public function loadTestBlade(IcecatTransformation $dataTransformation)
    {
        return view('crud::pages.data_transformation.test_script', [
            'dataTransformation' => $dataTransformation,
        ]);
    }

    // function to show the test blade
    public function loadRunBlade(IcecatTransformation $dataTransformation)
    {
        $categories = Category::query()
            ->whereIn('id', json_decode($dataTransformation->in_category))
            ->get();

        $dataTransformation->products_list = ! empty($dataTransformation->file_path)
            ? (Storage::exists($dataTransformation->file_path)
                ? Storage::get($dataTransformation->file_path)
                : json_encode([]))
            : json_encode([]);

        return view('crud::pages.data_transformation.run_script', [
            'dataTransformation' => $dataTransformation,
            'categories' => $categories,
        ]);
    }

    public function fetchSelectedProductsById(): JsonResponse
    {
        // $dataTransformation = DataTransformation::query()->where('id', request()->id)->firstOrFail('file_path');
        // $selectedProducts   = Storage::get('public/icecat-transformation-files/2022-08-24-1661352851.txt');
        // $products_list      = collect(json_decode($selectedProducts));
        $products_list = collect(session()->get('icecat_products_list'));

        $paginatePerPage = request()->pagination['resultsPerPage'] ?? 12;
        $currentPage = request()->pagination['currentPage'] ?? 1;

        $searchTerm = trim(request()->search);
        // Searching products by Product_Name, Product Code, Product ID
        $filtered_products_list = ! empty($searchTerm) ? $products_list->filter(function ($item) use ($searchTerm) {
            $fieldsToSearch = ['Product_Name', 'Product_Code', 'Product_Id'];
            foreach ($fieldsToSearch as $field) {
                if (stripos($item[$field], $searchTerm) !== false) {
                    return true;
                }
            }

            return false;
        }) : $products_list;

        $data = $this->paginate($filtered_products_list, $paginatePerPage, $currentPage);

        return response()->json($data);
    }

    public function paginate($items, $perPage, $page = null, $options = []): LengthAwarePaginator
    {
        $page = $page ?? (Paginator::resolveCurrentPage()
                ?: 1);
        $items = $items instanceof Collection
            ? $items
            : Collection::make($items);

        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }

    /**
     * @throws \JsonException
     */
    public function fetchSaveToSession(): JsonResponse
    {
        // $file = !empty(request()->file_path)
        //     ? request()->file_path
        //     : 'public/data-transformation-files/' . date('Y-m-d-') . time() . '.txt';

        session()->put('icecat_products_list', request()->products_list);
        session()->save();

        /****** Commented due to false toaster on Admin IcecatTransformation Create Page Start
* //        $products = session()->get('icecat_products_list');
* //
* //        return $products
* //            ? response()->json([
* //                'success' => true,
* //                'file_path' => '',
* //            ])
* //            : response()->json([
* //                'success' => false,
* //            ], 500);
* Commented due to false toaster on Admin IcecatTransformation Create Page End *****/

        return response()->json([
            'success' => true,
            'file_path' => '',
        ]);
    }

    public function fetchSaveJobs(Request $request)
    {

        $productList = $request->productList;
        $icecatDefinition = IcecatDefinition::find($request->transformationNamesId['id']);
        $contents = $this->getContentsToBeFetched($icecatDefinition);

        $icecatTransformation = IcecatTransformation::create([
            'name' => $request->transformationName,
            'icecat_definition_id' => $icecatDefinition->id,
            'rows' => count($productList),
        ]);

        foreach ($productList as $product) {
            $product = Product::find($product['Product_Id']);
            $details['icecatUsername'] = config('amplify.icecat.icecat_username');
            $details['contents'] = $contents;
            $details['product'] = $product;
            $details['manufacturer'] = $product->manufacturerr;

            ProcessIcecaProductsInformationJob::dispatch($details, $product, $icecatTransformation);
        }
        $this->crud->setSaveAction($request->_save_action);

        return $icecatTransformation;
    }

    public function getContentsToBeFetched($icecatDefinition)
    {
        $content = '';

        if ($icecatDefinition->brandChecked()) {
            $content .= 'Brand,';
        }

        if ($icecatDefinition->brandPartCodeChecked()) {
            $content .= 'BrandPartCode,';
        }

        if ($icecatDefinition->gtinChecked()) {
            $content .= 'GTIN,';
        }

        if ($icecatDefinition->brandLogoChecked()) {
            $content .= 'BrandLogo,';
        }

        if ($icecatDefinition->featuresChecked()) {
            $content .= 'FeaturesGroups,';
        }

        return $content;
    }

    public function fetchTestScript(Request $request): JsonResponse
    {
        /* Prepare data for transformation */
        $data = [
            'script' => (array) $request->input('script'),
            'fields' => (array) $request->input('fields'),
            'attributes' => (array) $request->input('attributes'),
            'variables' => (array) $request->input('variables'),
            'categories' => (array) $request->input('categories'),
            'productClassification' => (string) $request->input('productClassification'),
        ];

        /* Making data transformation by 'ExecuteScriptService' */
        $executeScriptService = new ExecuteScriptService;
        $responseData = $executeScriptService->validateScript($data);

        /* Returning response data */
        return response()->json($responseData);
    }

    /**
     * @throws \JsonException
     */
    public function fetchRunScript(): JsonResponse
    {
        /* Get data transformation by id */
        $dataTransformation = IcecatTransformation::query()
            ->where('id', request()->id)
            ->firstOrFail();

        /* Prepare data for transformation */
        $scriptsArr = preg_split("/\r\n|\n|\r/", $dataTransformation->scripts);
        $products_list = Session::get('icecat_products_list');
        $data = [
            'scriptsArr' => $scriptsArr,
            'appliesTo' => json_decode($dataTransformation->applies_to, false, 512, JSON_THROW_ON_ERROR)->name,
            'scriptType' => 'run_script',
            'userId' => backpack_auth()->user()->id,
        ];

        /* Making data transformation by dispatching 'ExecuteScriptJob' */
        collect($products_list)->map(function ($item) use ($data) {
            ExecuteScriptJob::dispatch((array) $item, $data);
        });

        /* Returning response */
        return response()->json([
            'success' => true,
        ])->setStatusCode(202);
    }
}

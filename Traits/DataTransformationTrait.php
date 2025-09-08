<?php

namespace Amplify\System\Utility\Traits;

use Amplify\System\Backend\Models\Category;
use Amplify\System\Jobs\DataTransformationParentJob;
use Amplify\System\Utility\Models\DataTransformation;
use Amplify\System\Utility\Services\DataTransformation\ExecuteScriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

trait DataTransformationTrait
{
    protected function setupCustomRoutes($segment, $routeName, $controller)
    {
        // /admin/test/script/{dataTransformation}
        Route::get($segment.'/test/script/{dataTransformation}', [
            'as' => $routeName.'.test.script',
            'uses' => $controller.'@loadTestBlade',
            'operation' => 'loadTestBlade',
        ]);

        // /admin/test/script/{dataTransformation}
        Route::get($segment.'/run/script/{dataTransformation}', [
            'as' => $routeName.'.run.script',
            'uses' => $controller.'@loadRunBlade',
            'operation' => 'loadRunBlade',
        ]);
    }

    // function to show the test blade
    public function loadTestBlade(DataTransformation $dataTransformation)
    {
        return view('crud::pages.data_transformation.test_script', [
            'dataTransformation' => $dataTransformation,
        ]);
    }

    // function to show the test blade
    public function loadRunBlade(DataTransformation $dataTransformation)
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
        $dataTransformation = DataTransformation::query()->where('id', request()->id)->firstOrFail('file_path');
        $selectedProducts = ! empty($dataTransformation->file_path)
            ? Storage::get($dataTransformation->file_path)
            : json_encode([]);
        $products_list = collect(json_decode($selectedProducts));
        $paginatePerPage = request()->pagination['resultsPerPage'] ?? 12;
        $currentPage = request()->pagination['currentPage'] ?? 1;

        // Searching products by Product_Name
        $filtered_products_list = request()->search ?? false
                ? $products_list->filter(function ($item) {
                    return stripos($item->Product_Name, trim(request()->search)) !== false;
                })
                : $products_list;

        /*
        // Searching products by Product_Name, Short_Description, Long_Description
        $filtered_products_list = request()->search ?? false
                ? $products_list->filter(function ($item) {
                    return false !== stripos($item->Product_Name, trim(request()->search))
                           or false !== stripos($item->Short_Description, trim(request()->search))
                           or false !== stripos($item->Long_Description, trim(request()->search));
                })
                : $products_list;
        */

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
    public function fetchSaveToFile(): JsonResponse
    {
        $file = ! empty(request()->file_path)
            ? request()->file_path
            : 'public/data-transformation-files/'.date('Y-m-d-').time().'.txt';

        $fileSaved = Storage::put($file, json_encode(request()->products_list, JSON_THROW_ON_ERROR));

        return $fileSaved
            ? response()->json([
                'success' => true,
                'file_path' => $file,
            ])
            : response()->json([
                'success' => false,
            ], 500);
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
            'productData' => $request->input('productData'),
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
        DataTransformationParentJob::dispatch(request()->id, backpack_auth()->user()->id);

        /* Returning response */
        return response()->json([
            'success' => true,
        ])->setStatusCode(202);
    }

    /**
     * @return string[]
     */
    private function getState($entity): array
    {
        $state = [
            'className' => 'la-spinner la-pulse text-warning',
            'title' => 'Pending or Running',
        ];

        $rows_count = $entity->row_count;
        $success_count = $entity->success_count;
        $failed_count = $entity->failed_count;
        $runCount = $success_count + $failed_count;
        $leftCount = $rows_count - $runCount;
        $leftNone = $leftCount === 0;

        if ($success_count === 0 && $failed_count === 0) {
            $state = [
                'className' => '',
                'title' => 'Pending',
            ];
        }

        if ($leftNone && $rows_count > 0) {
            $state = [
                'className' => 'la-check text-success',
                'title' => 'Done Without Any Error',
            ];
        }

        if ($leftNone && $failed_count) {
            $state = [
                'className' => 'la-check text-danger',
                'title' => "Done With $failed_count Error(s)",
            ];
        }

        if ($leftCount > 0) {
            $state = [
                'className' => 'la-sync la-pulse text-info',
                'title' => 'Running',
            ];
        }

        if ($entity->status === 'failed') {
            $state = [
                'className' => 'la-sync la-times text-danger',
                'title' => 'Failed',
            ];
        }

        return $state;
    }
}

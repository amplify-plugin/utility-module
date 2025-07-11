<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'backpack'),
    'middleware' => array_merge(config('backpack.base.web_middleware', ['web']),
        (array) config('backpack.base.middleware_key', 'admin')),
    ['admin_password_reset_required'],
    'namespace' => 'Amplify\System\Utility\Http\Controllers',
], function () {
    Route::crud('audit', 'AuditCrudController');
    Route::crud('api-log', 'ApiLogCrudController');
    Route::crud('failed-job', 'FailedJobCrudController');
    Route::crud('job', 'JobCrudController');
    Route::crud('mail-log', 'MailLogCrudController');
    Route::crud('data-transformation', 'DataTransformationCrudController');
    Route::post('transform/execute', 'DataTransformationCrudController@execute')->name('admin.transform.execute');
    Route::post('transform/validate-script', 'DataTransformationCrudController@validateScript')->name('admin.transform.validateScript');
    Route::crud('icecat-definition', 'IcecatDefinitionCrudController');
    Route::crud('icecat-transformation', 'IcecatTransformationCrudController');
    Route::crud('import-definition', 'ImportDefinitionCrudController');
    Route::post('import-definition/upload-file', 'ImportDefinitionCrudController@handleUploadFile');
    Route::crud('import-job', 'ImportJobCrudController');
    Route::post('import-job/upload-file', 'ImportJobCrudController@handleUploadFile');
    Route::crud('export', 'ExportCrudController');
    Route::crud('scheduled-job', 'ScheduledJobCrudController');
    Route::get('scheduled-job/run-now/{id}', 'ScheduledJobCrudController@runNow');
    Route::crud('backup', 'BackupCrudController');
});

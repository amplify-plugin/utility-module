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
    Route::post('export/products/download', 'ExportCrudController@downloadProducts')->name('admin.export.products.download');
    Route::post('export/manufacturers/download', 'ExportCrudController@downloadManufacturers')->name('admin.export.manufacturers.download');
    Route::post('export/sql/preview', 'ExportCrudController@previewSql')->name('admin.export.sql.preview');
    Route::post('export/sql/download', 'ExportCrudController@downloadSql')->name('admin.export.sql.download');
    Route::get('export/sql/history', 'ExportCrudController@sqlHistory')->name('admin.export.sql.history');
    Route::delete('export/sql/history/{id}', 'ExportCrudController@deleteSqlHistory')->name('admin.export.sql.history.delete');
    Route::crud('scheduled-job', 'ScheduledJobCrudController');
    Route::get('scheduled-job/run-now/{id}', 'ScheduledJobCrudController@runNow');
    Route::crud('backup', 'BackupCrudController');
});

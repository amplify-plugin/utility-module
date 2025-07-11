<?php

namespace Amplify\System\Utility\Repositories\Interfaces;

use Illuminate\Http\Request;

interface ImportJobInterface
{
    /**
     * @return mixed
     */
    public function processImportJob(Request $request);
}

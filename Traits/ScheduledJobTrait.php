<?php

namespace Amplify\System\Utility\Traits;

use Amplify\System\Utility\Models\ImportJob;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Prologue\Alerts\Facades\Alert;

trait ScheduledJobTrait
{
    public function runNow($id): RedirectResponse
    {
        DB::table('jobs')
            ->where('id', $id)
            ->update([
                'queue' => 'high',
                'available_at' => Carbon::now()->timestamp,
            ]);

        Alert::add('success', 'Job has been started!')->flash();

        return redirect()->back();
    }

    protected function getJobName(): ?string
    {
        return $this->{"get{$this->jobType}Name"}();
    }

    private function getImportJobName(): string
    {
        $importJob = ImportJob::query()
            ->with('importDefinition')
            ->findOrFail($this->request['import_job_id']);

        $importJobUri = route('import-job.show', $importJob->id);

        return "<a href='$importJobUri' target='_blank' title='Show Job Details'>"
               .($importJob->importDefinition->local_name ?? '<code>Not Found</code>').' - '.$importJob->id
               .'</a>';
    }
}

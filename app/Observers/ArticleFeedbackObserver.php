<?php

namespace App\Observers;

use App\Models\ArticleFeedback;
use Illuminate\Support\Facades\Cache;

class ArticleFeedbackObserver
{
    /**
     * Handle the ArticleFeedback "created" event.
     */
    public function created(ArticleFeedback $feedback): void
    {
        Cache::forget('admin_dashboard_version');
    }

    /**
     * Handle the ArticleFeedback "updated" event.
     */
    public function updated(ArticleFeedback $feedback): void
    {
        Cache::forget('admin_dashboard_version');
    }

    /**
     * Handle the ArticleFeedback "deleted" event.
     */
    public function deleted(ArticleFeedback $feedback): void
    {
        Cache::forget('admin_dashboard_version');
    }
}

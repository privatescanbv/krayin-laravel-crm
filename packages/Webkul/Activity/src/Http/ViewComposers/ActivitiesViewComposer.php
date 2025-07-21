<?php

namespace Webkul\Activity\Http\ViewComposers;

use Illuminate\View\View;
use Webkul\Activity\Services\ViewService;

class ActivitiesViewComposer
{
    /**
     * Create a new view composer.
     */
    public function __construct(
        protected ?ViewService $viewService = null
    ) {}

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $views = [
            'for_me' => [
                'key' => 'for_me',
                'label' => 'Voor mij',
                'description' => 'Activiteiten toegewezen aan mij',
                'is_default' => true,
            ]
        ];
        $currentView = 'for_me';

        $viewService = $this->viewService ?? app(ViewService::class);
        
        if ($viewService) {
            $views = $viewService->getAvailableViews();
            $currentView = request()->get('view', $viewService->getDefaultView()['key']);
        }

        $view->with('views', $views);
        $view->with('currentView', $currentView);
    }
}
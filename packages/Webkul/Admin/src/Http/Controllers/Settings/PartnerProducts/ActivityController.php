<?php

namespace Webkul\Admin\Http\Controllers\Settings\PartnerProducts;

use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Email\Repositories\EmailRepository;

class ActivityController extends Controller
{
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected EmailRepository $emailRepository
    ) {}

    public function index($id)
    {
        $activities = $this->activityRepository
            ->leftJoin('partner_product_activities', 'activities.id', '=', 'partner_product_activities.activity_id')
            ->where('partner_product_activities.partner_product_id', $id)
            ->get();

        return ActivityResource::collection($this->concatEmail($activities));
    }

    public function concatEmail($activities)
    {
        return $activities->sortByDesc('id')->sortByDesc('created_at');
    }
}


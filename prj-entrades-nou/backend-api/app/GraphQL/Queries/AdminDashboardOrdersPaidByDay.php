<?php

namespace App\GraphQL\Queries;

use App\Services\Admin\AdminDashboardMetricsService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AdminDashboardOrdersPaidByDay
{
    public function __invoke (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        $days = 30;
        if (isset($args['days'])) {
            $days = (int) $args['days'];
        }

        return app(AdminDashboardMetricsService::class)->ordersPaidByDay($days);
    }
}

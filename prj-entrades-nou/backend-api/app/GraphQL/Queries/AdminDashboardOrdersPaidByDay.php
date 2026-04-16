<?php

namespace App\GraphQL\Queries;

//================================ NAMESPACES / IMPORTS ============

use App\Services\Admin\AdminDashboardMetricsService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class AdminDashboardOrdersPaidByDay
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array
    {
        $days = 30;
        if (isset($args['days'])) {
            $days = (int) $args['days'];
        }

        return app(AdminDashboardMetricsService::class)->ordersPaidByDay($days);
    }
}

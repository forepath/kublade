<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Kubernetes\Clusters\Cluster;
use App\Models\Projects\Deployments\Deployment;
use App\Models\Projects\Projects\Project;
use App\Models\Projects\Templates\Template;
use Closure;
use Illuminate\Http\Request;

/**
 * Class IdentifyOnboardingStatus.
 *
 * This class is the middleware for identifying the onboarding status.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class IdentifyOnboardingStatus
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $firstProject                   = Project::first();
        $hasProjects                    = !empty($firstProject);
        $hasClusterProvisionerTemplates = Template::where('type', '=', 'cluster')->count() > 0;
        $hasClusters                    = Cluster::query()->count() > 0;
        $hasApplicationTemplates        = Template::where('type', '=', 'application')->count() > 0;
        $hasApplications                = Deployment::query()->count() > 0;

        $request->attributes->add(['onboarding_status' => (object) [
            'projects'                      => $hasProjects,
            'cluster_provisioner_templates' => $hasClusterProvisionerTemplates,
            'clusters'                      => $hasClusters,
            'application_templates'         => $hasApplicationTemplates,
            'applications'                  => $hasApplications,
            'first_project'                 => $firstProject,
        ]]);

        return $next($request);
    }
}

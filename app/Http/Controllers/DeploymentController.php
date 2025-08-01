<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Kubernetes\Clusters\Cluster;
use App\Models\Kubernetes\Resources\PodLog;
use App\Models\Projects\Deployments\Deployment;
use App\Models\Projects\Deployments\DeploymentCommit;
use App\Models\Projects\Deployments\DeploymentData;
use App\Models\Projects\Deployments\DeploymentLimit;
use App\Models\Projects\Deployments\DeploymentLink;
use App\Models\Projects\Deployments\DeploymentSecretData;
use App\Models\Projects\Projects\Project;
use App\Models\Projects\Templates\Template;
use App\Models\Projects\Templates\TemplateField;
use App\Models\Projects\Templates\TemplateFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Class DeploymentController.
 *
 * This class is the controller for the deployment actions.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class DeploymentController extends Controller
{
    /**
     * Show the deployment index page.
     *
     * @param string $project_id
     * @param string $deployment_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function page_index(string $project_id, string $deployment_id = null)
    {
        $request    = request();
        $deployment = Deployment::where('id', '=', $deployment_id)->first();
        $datapoints = collect();

        if ($deployment_id && $request->tab === 'metrics') {
            $buildBaseQuery = function ($query, $from, $to) {
                if ($from) {
                    $query = $query->where('created_at', '>=', $from);
                }

                if ($to) {
                    $query = $query->where('created_at', '<=', $to);
                }

                return $query;
            };

            $this->calculateTimeframes(
                $request->from ?? Carbon::now()->subWeek()->startOfDay()->toISOString(),
                $request->to ?? Carbon::now()->endOfDay()->toISOString(),
                $request->aggregation ?? 'day'
            )->each(function ($timeframe) use ($deployment, $buildBaseQuery, &$datapoints) {
                $metric = $buildBaseQuery($deployment->metrics(), $timeframe->from, $timeframe->to)
                    ->select(DB::raw('AVG(storage_bytes) storage_bytes, AVG(memory_bytes) memory_bytes, AVG(cpu_core_usage) cpu_core_usage'))
                    ->first()
                    ?->toArray() ?? [
                        'storage_bytes'  => 0,
                        'memory_bytes'   => 0,
                        'cpu_core_usage' => 0,
                    ];

                $metric['storage_bytes']  = $metric['storage_bytes'] ? $metric['storage_bytes'] : 0;
                $metric['memory_bytes']   = $metric['memory_bytes'] ? $metric['memory_bytes'] : 0;
                $metric['cpu_core_usage'] = $metric['cpu_core_usage'] ? $metric['cpu_core_usage'] : 0;

                $trafficBytesIn  = 0;
                $trafficBytesOut = 0;

                $deployment->namespaces->each(function ($namespace) use ($timeframe, $buildBaseQuery, &$trafficBytesIn, &$trafficBytesOut) {
                    $sub = $buildBaseQuery($namespace->containerAdvisoryMetrics(), $timeframe->from, $timeframe->to)
                        ->whereIn('key', [
                            'container_network_receive_bytes_total',
                            'container_network_transmit_bytes_total',
                        ])
                        ->select('key', 'pod_id', DB::raw('MAX(value) - MIN(value) AS diff'))
                        ->groupBy('pod_id', 'key', 'interface');

                    DB::table(DB::raw("({$sub->toSql()}) as sub"))
                        ->mergeBindings($sub->getQuery()->getQuery())
                        ->select('key', DB::raw('SUM(diff) AS total'))
                        ->groupBy('key')
                        ->orderBy('key', 'ASC')
                        ->each(function ($result) use (&$trafficBytesIn, &$trafficBytesOut) {
                            if ($result->key === 'container_network_receive_bytes_total') {
                                $trafficBytesIn += (int) $result->total;
                            } elseif ($result->key === 'container_network_transmit_bytes_total') {
                                $trafficBytesOut += (int) $result->total;
                            }
                        });
                });

                $metric['traffic_bytes_in']  = $trafficBytesIn >= 0 ? $trafficBytesIn : $trafficBytesIn * (-1);
                $metric['traffic_bytes_out'] = $trafficBytesOut >= 0 ? $trafficBytesOut : $trafficBytesOut * (-1);

                $metric['cpu_core_percentage']   = $metric['cpu_core_usage'] * 100;
                $metric['memory_gigabytes']      = $metric['memory_bytes'] / 1024 / 1024 / 1024;
                $metric['storage_gigabytes']     = $metric['storage_bytes'] / 1024 / 1024 / 1024;
                $metric['traffic_gigabytes_in']  = $metric['traffic_bytes_in'] / 1024 / 1024 / 1024;
                $metric['traffic_gigabytes_out'] = $metric['traffic_bytes_out'] / 1024 / 1024 / 1024;

                $datapoints->push([
                    'timestamp' => $timeframe->to->toISOString(),
                    'values'    => $metric,
                ]);
            });
        }

        $file = null;

        if ($deployment && $request->tab === 'files' && $request->file_id) {
            $file = TemplateFile::where('id', $request->file_id)->first();
        }

        $log = null;

        if ($deployment && $request->tab === 'logs' && $request->log_id) {
            $log = PodLog::where('id', $request->log_id)
                ->first()
                ->makeVisible('logs');
        }

        $networkPolicy = null;

        if ($deployment && $request->tab === 'network-policies' && $request->network_policy_id && $request->network_policy_id !== 'new') {
            $networkPolicy = DeploymentLink::where('id', $request->network_policy_id)->first();
        }

        return view('deployment.index', [
            'deployments'   => Deployment::where('project_id', '=', $project_id)->paginate(10),
            'deployment'    => $deployment,
            'metrics'       => $datapoints,
            'file'          => $file,
            'log'           => $log,
            'networkPolicy' => $networkPolicy,
        ]);
    }

    /**
     * Calculate the timeframes.
     *
     * @deprecated
     *
     * TODO: Move to a helper function.
     *
     * @param string $from
     * @param string $to
     * @param string $aggregation
     * @param array  $timeframes
     *
     * @return \Illuminate\Support\Collection
     */
    private function calculateTimeframes($from, $to, $aggregation, $timeframes = null)
    {
        $carbonFrom = Carbon::parse($from);
        $carbonTo   = Carbon::parse($to);

        switch ($aggregation) {
            case 'minute':
                $nextTarget = (clone $carbonFrom)->addMinute();

                break;
            case 'hour':
                $nextTarget = (clone $carbonFrom)->addHour();

                break;
            case 'day':
                $nextTarget = (clone $carbonFrom)->addDay();

                break;
            case 'week':
                $nextTarget = (clone $carbonFrom)->addWeek();

                break;
            case 'month':
                $nextTarget = (clone $carbonFrom)->addMonth();

                break;
            case 'quarter':
                $nextTarget = (clone $carbonFrom)->addQuarter();

                break;
            case 'year':
                $nextTarget = (clone $carbonFrom)->addYear();

                break;
            case 'all':
                $nextTarget = (clone $carbonTo);

                break;
        }

        if (!$timeframes) {
            $timeframes = collect();
        }

        $timeframes->push((object) [
            'from' => $carbonFrom,
            'to'   => $nextTarget->gte($carbonTo) ? $carbonTo : $nextTarget,
        ]);

        if ($nextTarget->lt($carbonTo)) {
            return $this->calculateTimeframes($nextTarget->addSecond()->toISOString(), $to, $aggregation, $timeframes);
        } else {
            return $timeframes;
        }
    }

    /**
     * Show the deployment add page.
     *
     * @param string $project_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function page_add(string $project_id)
    {
        return view('deployment.add', [
            'clusters'  => Cluster::all(),
            'templates' => Template::where('type', '=', 'application')->get(),
        ]);
    }

    /**
     * Add the deployment.
     *
     * @param string  $project_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_add(string $project_id, Request $request)
    {
        Validator::make($request->toArray(), [
            'template_id' => ['required', 'string'],
            'cluster_id'  => ['required', 'string'],
            'name'        => ['required', 'string'],
        ])->validate();

        /**
         * @var Template $template
         * @var Project  $project
         */
        if (
            ! empty(
                $project = Project::where('id', '=', $project_id)
                    ->first()
            ) &&
            ! empty(
                $template = Template::where('id', '=', $request->template_id)
                    ->where('type', '=', 'application')
                    ->first()
            ) &&
            ! empty(
                $cluster = Cluster::where('id', '=', $request->cluster_id)
                    ->first()
            )
        ) {
            $validationRules = [];

            $template->fields->each(function (TemplateField $field) use ($template, &$validationRules) {
                if (! $field->set_on_create) {
                    return;
                }

                $rules = [];

                if ($field->required) {
                    $rules[] = 'required';
                } else {
                    $rules[] = 'nullable';
                }

                switch ($field->type) {
                    case 'input_number':
                    case 'input_range':
                        $rules[] = 'numeric';

                        if (! empty($field->min)) {
                            $rules[] = 'min:' . $field->min;
                        }

                        if (! empty($field->max)) {
                            $rules[] = 'max:' . $field->max;
                        }

                        if (! empty($field->step)) {
                            $rules[] = 'multiple_of:' . $field->step;
                        }

                        break;
                    case 'input_radio':
                    case 'input_radio_image':
                    case 'select':
                        $availableOptions = $field->options
                            ->pluck('value')
                            ->toArray();

                        if (! empty($field->value)) {
                            $availableOptions[] = $field->value;
                        }

                        $rules[] = Rule::in($availableOptions);

                        break;
                    case 'input_text':
                    case 'textarea':
                    default:
                        $rules[] = 'string';

                        break;
                }

                $validationRules['data.' . $template->id . '.' . $field->key] = $rules;
            });

            Validator::make($request->toArray(), $validationRules)->validate();

            /* @var Deployment $deployment */
            if (
                $deployment = Deployment::create([
                    'user_id'      => Auth::id(),
                    'project_id'   => $project->id,
                    'namespace_id' => null,
                    'cluster_id'   => $cluster->id,
                    'template_id'  => $template->id,
                    'name'         => $request->name,
                    'uuid'         => Str::uuid(),
                ])
            ) {
                $requestFields = (object) (array_key_exists($deployment->template->id, $request->data ?? []) ? $request->data[$deployment->template->id] : []);

                $template->fields->each(function (TemplateField $field) use ($requestFields, $deployment) {
                    if ($field->type === 'input_radio' || $field->type === 'input_radio_image') {
                        $option = null;

                        if ($field->set_on_create) {
                            $option = $field->options
                                ->where('value', '=', $requestFields->{$field->key})
                                ->first();
                        }

                        if (empty($option)) {
                            $option = $field->options
                                ->where('default', '=', true)
                                ->first();
                        }

                        if (! empty($option)) {
                            $value = $option->value;
                        }

                        if (empty($value)) {
                            $value = $requestFields->{$field->key};
                        }
                    } else {
                        if ($field->set_on_create) {
                            $value = $requestFields->{$field->key} ?? '';
                        } else {
                            $value = $field->value ?? '';
                        }
                    }

                    if ($field->secret) {
                        DeploymentSecretData::create([
                            'deployment_id'     => $deployment->id,
                            'template_field_id' => $field->id,
                            'key'               => $field->key,
                            'value'             => $value,
                        ]);
                    } else {
                        DeploymentData::create([
                            'deployment_id'     => $deployment->id,
                            'template_field_id' => $field->id,
                            'key'               => $field->key,
                            'value'             => $value,
                        ]);
                    }
                });

                DeploymentLimit::create([
                    'deployment_id' => $deployment->id,
                    'is_active'     => $request->limit ? $request->limit['is_active'] ?? false : false,
                    'memory'        => $request->limit ? $request->limit['memory'] ?? 0 : 0,
                    'cpu'           => $request->limit ? $request->limit['cpu'] ?? 0 : 0,
                ]);

                return redirect()->route('deployment.index', ['project_id' => $project_id, 'deployment_id' => $deployment->id])->with('success', __('Deployment created.'));
            }
        }

        return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
    }

    /**
     * Show the deployment update page.
     *
     * @param string $project_id
     * @param string $deployment_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function page_update(string $project_id, string $deployment_id)
    {
        return view('deployment.update', [
            'deployment' => Deployment::find($deployment_id),
        ]);
    }

    /**
     * Update the deployment.
     *
     * @param string  $project_id
     * @param string  $deployment_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_update(string $project_id, string $deployment_id, Request $request)
    {
        Validator::make([
            'deployment_id' => $deployment_id,
            'name'          => $request->name,
        ], [
            'deployment_id' => ['required', 'string'],
            'name'          => ['required', 'string'],
        ])->validate();

        /**
         * @var Deployment $deployment
         */
        if (
            ! empty(
                $deployment = Deployment::where('id', '=', $deployment_id)
                    ->first()
            )
        ) {
            $validationRules = [];

            $deployment->template->fields->each(function (TemplateField $field) use ($deployment, &$validationRules) {
                if (! $field->set_on_update) {
                    return;
                }

                $rules = [];

                if ($field->required) {
                    $rules[] = 'required';
                } else {
                    $rules[] = 'nullable';
                }

                switch ($field->type) {
                    case 'input_number':
                    case 'input_range':
                        $rules[] = 'numeric';

                        if (! empty($field->min)) {
                            $rules[] = 'min:' . $field->min;
                        }

                        if (! empty($field->max)) {
                            $rules[] = 'max:' . $field->max;
                        }

                        if (! empty($field->step)) {
                            $rules[] = 'multiple_of:' . $field->step;
                        }

                        break;
                    case 'input_radio':
                    case 'input_radio_image':
                    case 'select':
                        $availableOptions = $field->options
                            ->pluck('value')
                            ->toArray();

                        if (! empty($field->value)) {
                            $availableOptions[] = $field->value;
                        }

                        $rules[] = Rule::in($availableOptions);

                        break;
                    case 'input_text':
                    case 'textarea':
                    default:
                        $rules[] = 'string';

                        break;
                }

                $validationRules['data.' . $deployment->template->id . '.' . $field->key] = $rules;
            });

            Validator::make($request->toArray(), $validationRules)->validate();

            $requestFields = (object) (array_key_exists($deployment->template->id, $request->data ?? []) ? $request->data[$deployment->template->id] : []);

            $deployment->template->fields->each(function (TemplateField $field) use ($requestFields, $deployment) {
                if (! $field->set_on_update) {
                    return;
                }

                if ($field->type === 'input_radio' || $field->type === 'input_radio_image') {
                    $option = $field->options
                        ->where('value', '=', $requestFields->{$field->key})
                        ->first();

                    if (empty($option)) {
                        $option = $field->options
                            ->where('default', '=', true)
                            ->first();
                    }

                    if (! empty($option)) {
                        $value = $option->value;
                    }

                    if (empty($value)) {
                        $value = $requestFields->{$field->key};
                    }
                } else {
                    $value = $requestFields->{$field->key} ?? '';
                }

                if ($field->secret) {
                    $deployment->deploymentSecretData->where('template_field_id', '=', $field->id)->each(function (DeploymentSecretData $deploymentSecretData) use ($value) {
                        $deploymentSecretData->update([
                            'value' => $value,
                        ]);
                    });
                } else {
                    $deployment->deploymentData->where('template_field_id', '=', $field->id)->each(function (DeploymentData $deploymentData) use ($value) {
                        $deploymentData->update([
                            'value' => $value,
                        ]);
                    });
                }
            });

            DeploymentLimit::updateOrCreate([
                'deployment_id' => $deployment->id,
            ], [
                'is_active' => $request->limit ? $request->limit['is_active'] ?? false : false,
                'memory'    => $request->limit ? $request->limit['memory'] ?? 0 : 0,
                'cpu'       => $request->limit ? $request->limit['cpu'] ?? 0 : 0,
            ]);

            $deployment->update([
                'name' => $request->name,
                ...($deployment->deployed_at ? [
                    'update'      => true,
                    'approved_at' => null,
                ] : []),
            ]);

            return redirect()->route('deployment.index', ['project_id' => $project_id, 'deployment_id' => $deployment->id])->with('success', __('Deployment updated.'));
        }

        return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
    }

    /**
     * Delete the deployment.
     *
     * @param string $project_id
     * @param string $deployment_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_delete(string $project_id, string $deployment_id)
    {
        Validator::make([
            'deployment_id' => $deployment_id,
        ], [
            'deployment_id' => ['required', 'string'],
        ])->validate();

        /**
         * @var Deployment $deployment
         */
        if (
            ! empty(
                $deployment = Deployment::where('id', '=', $deployment_id)
                    ->first()
            )
        ) {
            $deployment->update([
                'delete' => true,
            ]);

            return redirect()->route('deployment.index', ['project_id' => $project_id])->with('success', __('Deployment deleted.'));
        }

        return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
    }

    /**
     * Create the network policy.
     *
     * @param string  $project_id
     * @param string  $deployment_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_put_network_policy(string $project_id, string $deployment_id, Request $request)
    {
        Validator::make($request->toArray(), [
            'source_deployment_id' => ['required', 'string'],
            'target_deployment_id' => ['required', 'string'],
            'network_policy_id'    => ['nullable', 'string'],
        ])->validate();

        if (
            DeploymentLink::where('source_deployment_id', '=', $request->source_deployment_id)
                ->where('target_deployment_id', '=', $request->target_deployment_id)
                ->exists()
        ) {
            return redirect()->back()->with('warning', __('Network policy already exists.'));
        }

        $sourceDeployment = Deployment::where('id', '=', $request->source_deployment_id)->first();

        if (empty($sourceDeployment)) {
            return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
        }

        $targetDeployment = Deployment::where('id', '=', $request->target_deployment_id)->first();

        if (empty($targetDeployment) || $sourceDeployment->id === $targetDeployment->id) {
            return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
        }

        if ($request->network_policy_id) {
            $networkPolicy = DeploymentLink::where('id', '=', $request->network_policy_id)->first();

            if (empty($networkPolicy)) {
                return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
            }

            $networkPolicy->update([
                'source_deployment_id' => $sourceDeployment->id,
                'target_deployment_id' => $targetDeployment->id,
            ]);
        } else {
            DeploymentLink::create([
                'source_deployment_id' => $sourceDeployment->id,
                'target_deployment_id' => $targetDeployment->id,
            ]);
        }

        if (
            ! empty($targetDeployment->deployed_at) &&
            ! $targetDeployment->delete
        ) {
            $targetDeployment->update([
                'update'      => true,
                'approved_at' => null,
            ]);
        }

        return redirect()->route('deployment.details', ['project_id' => $project_id, 'deployment_id' => $targetDeployment->id, 'tab' => 'network-policies'])->with('success', __('Network policy created.'));
    }

    /**
     * Delete the network policy.
     *
     * @param string $project_id
     * @param string $deployment_id
     * @param string $network_policy_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_delete_network_policy(string $project_id, string $deployment_id, string $network_policy_id)
    {
        Validator::make([
            'deployment_id'     => $deployment_id,
            'network_policy_id' => $network_policy_id,
        ], [
            'deployment_id'     => ['required', 'string'],
            'network_policy_id' => ['required', 'string'],
        ])->validate();

        $networkPolicy = DeploymentLink::where('id', '=', $network_policy_id)->first();

        if (empty($networkPolicy)) {
            return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
        }

        $networkPolicy->delete();

        return redirect()->route('deployment.details', ['project_id' => $project_id, 'deployment_id' => $deployment_id, 'tab' => 'network-policies'])->with('success', __('Network policy deleted.'));
    }

    /**
     * Revert the commit.
     *
     * @param string $project_id
     * @param string $deployment_id
     * @param string $commit_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_revert_commit(string $project_id, string $deployment_id, string $commit_id)
    {
        Validator::make([
            'deployment_id' => $deployment_id,
            'commit_id'     => $commit_id,
        ], [
            'deployment_id' => ['required', 'string'],
            'commit_id'     => ['required', 'string'],
        ])->validate();

        $commit = DeploymentCommit::where('id', '=', $commit_id)->first();

        if (
            empty($commit) ||
            $commit->deployment->delete
        ) {
            return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
        }

        $commit->diff->each(function ($diff) use ($commit) {
            if ($diff['type'] === 'plain') {
                $commit->deployment->deploymentData->where('key', $diff['key'])->each(function (DeploymentData $deploymentData) use ($diff) {
                    $deploymentData->update([
                        'value' => $diff['previous'],
                    ]);
                });
            } else {
                $commit->deployment->deploymentSecretData->where('key', $diff['key'])->each(function (DeploymentSecretData $secretData) use ($diff) {
                    $secretData->update([
                        'value' => $diff['previous'],
                    ]);
                });
            }

            if (!empty($commit->deployment->deployed_at)) {
                $commit->deployment->update([
                    'update'      => true,
                    'approved_at' => null,
                ]);
            }
        });

        return redirect()->route('deployment.details', ['project_id' => $project_id, 'deployment_id' => $deployment_id, 'tab' => 'versions'])->with('success', __('Commit reverted.'));
    }

    /**
     * Approve the deployment.
     *
     * @param string $project_id
     * @param string $deployment_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_approve(string $project_id, string $deployment_id)
    {
        Validator::make([
            'deployment_id' => $deployment_id,
        ], [
            'deployment_id' => ['required', 'string'],
        ])->validate();

        $deployment = Deployment::where('id', '=', $deployment_id)->first();

        if (empty($deployment)) {
            return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
        }

        if ($deployment->approved_at) {
            return redirect()->back()->with('warning', __('Deployment already approved.'));
        }

        $deployment->update([
            'approved_at' => Carbon::now(),
        ]);

        return redirect()->back()->with('success', __('Deployment approved.'));
    }
}

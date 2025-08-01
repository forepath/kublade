<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Helpers\API\Response;
use App\Http\Controllers\Controller;
use App\Models\Kubernetes\Clusters\Cluster;
use App\Models\Kubernetes\Clusters\ClusterData;
use App\Models\Kubernetes\Clusters\ClusterEnvironmentVariable;
use App\Models\Kubernetes\Clusters\ClusterSecretData;
use App\Models\Kubernetes\Clusters\GitCredential;
use App\Models\Kubernetes\Clusters\K8sCredential;
use App\Models\Kubernetes\Clusters\Ns;
use App\Models\Kubernetes\Clusters\Resource;
use App\Models\Projects\Templates\Template;
use App\Models\Projects\Templates\TemplateEnvironmentVariable;
use App\Models\Projects\Templates\TemplateField;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Class ClusterController.
 *
 * This class is the controller for the cluster actions.
 *
 * @OA\Tag(
 *     name="Clusters",
 *     description="Endpoints for cluster management"
 * )
 *
 * @OA\Parameter(
 *     name="cluster_id",
 *     in="path",
 *     required=true,
 *     description="The ID of the cluster",
 *
 *     @OA\Schema(type="string")
 * )
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class ClusterController extends Controller
{
    /**
     * List clusters.
     *
     * @OA\Get(
     *     path="/api/projects/{project_id}/clusters",
     *     summary="List clusters",
     *     tags={"Clusters"},
     *
     *     @OA\Parameter(ref="#/components/parameters/project_id"),
     *     @OA\Parameter(ref="#/components/parameters/cursor"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Clusters retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Clusters retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="clusters", type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/Cluster")
     *                 ),
     *
     *                 @OA\Property(property="links", type="object",
     *                     @OA\Property(property="next", type="string"),
     *                     @OA\Property(property="prev", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $project_id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function action_list(string $project_id)
    {
        $validator = Validator::make([
            'project_id' => $project_id,
        ], [
            'project_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $clusters = Cluster::where('project_id', $project_id)->cursorPaginate(10);

        return Response::generate(200, 'success', 'Clusters retrieved successfully', [
            'clusters' => collect($clusters->items())->map(function ($item) {
                return $item->toArray();
            }),
            'links' => [
                'next' => $clusters->nextCursor()?->encode(),
                'prev' => $clusters->previousCursor()?->encode(),
            ],
        ]);
    }

    /**
     * Get a cluster.
     *
     * @OA\Get(
     *     path="/api/projects/{project_id}/clusters/{cluster_id}",
     *     summary="Get a cluster",
     *     tags={"Clusters"},
     *
     *     @OA\Parameter(ref="#/components/parameters/project_id"),
     *     @OA\Parameter(ref="#/components/parameters/cluster_id"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Cluster retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Cluster retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="cluster", ref="#/components/schemas/Cluster")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFoundResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $project_id
     * @param string $cluster_id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function action_get(string $project_id, string $cluster_id)
    {
        $validator = Validator::make([
            'cluster_id' => $cluster_id,
        ], [
            'cluster_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $cluster = Cluster::where('id', $cluster_id)->first();

        if (!$cluster) {
            return Response::generate(404, 'error', 'Cluster not found');
        }

        return Response::generate(200, 'success', 'Cluster retrieved successfully', [
            'cluster' => $cluster->toArray(),
        ]);
    }

    /**
     * Add a new cluster.
     *
     * @OA\Post(
     *     path="/api/projects/{project_id}/clusters",
     *     summary="Add a new cluster",
     *     tags={"Clusters"},
     *
     *     @OA\Parameter(ref="#/components/parameters/project_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="git", type="object",
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="branch", type="string"),
     *                 @OA\Property(property="credentials", type="string"),
     *                 @OA\Property(property="username", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="base_path", type="string"),
     *             ),
     *             @OA\Property(property="k8s", type="object",
     *                 @OA\Property(property="api_url", type="string"),
     *                 @OA\Property(property="kubeconfig", type="string"),
     *                 @OA\Property(property="service_account_token", type="string"),
     *                 @OA\Property(property="node_prefix", type="string", nullable=true),
     *             ),
     *             @OA\Property(property="namespace", type="object",
     *                 @OA\Property(property="utility", type="string"),
     *                 @OA\Property(property="ingress", type="string"),
     *             ),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Cluster created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Cluster created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="cluster", ref="#/components/schemas/Cluster")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function action_add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_id'     => ['nullable', 'string', 'max:255'],
            'name'            => ['required', 'string', 'max:255'],
            'git'             => ['required', 'array'],
            'git.url'         => ['required', 'string', 'max:255'],
            'git.branch'      => ['required', 'string', 'max:255'],
            'git.credentials' => ['required', 'string'],
            'git.username'    => ['required', 'string', 'max:255'],
            'git.email'       => ['required', 'email', 'max:255'],
            'git.base_path'   => ['required', 'string', 'max:255'],
            'k8s'             => ['required', 'array'],
            'k8s.api_url'     => ['required', 'string', 'max:255'],
            ...(! empty($request->template_id) ? [
                'k8s.kubeconfig' => ['required', 'string'],
            ] : []),
            'k8s.service_account_token' => ['required', 'string'],
            'k8s.node_prefix'           => ['nullable', 'string', 'max:255'],
            'namespace'                 => ['required', 'array'],
            'namespace.utility'         => ['required', 'string', 'max:255'],
            'namespace.ingress'         => ['required', 'string', 'max:255'],
            'resources'                 => ['required', 'array'],
            'resources.alert'           => ['required', 'array'],
            'resources.alert.cpu'       => ['required', 'numeric'],
            'resources.alert.memory'    => ['required', 'numeric'],
            'resources.alert.storage'   => ['required', 'numeric'],
            'resources.alert.pods'      => ['required', 'numeric'],
            'resources.limit'           => ['required', 'array'],
            'resources.limit.cpu'       => ['required', 'numeric'],
            'resources.limit.memory'    => ['required', 'numeric'],
            'resources.limit.storage'   => ['required', 'numeric'],
            'resources.limit.pods'      => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $cluster = Cluster::create([
                'project_id' => $request->project_id,
                'user_id'    => Auth::user()->id,
                'name'       => $request->name,
            ])
        ) {
            if (
                ! empty($request->template_id) &&
                ! empty(
                    $template = Template::where('id', '=', $request->template_id)
                        ->where('type', '=', 'cluster')
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

                $template->environmentVariables->each(function (TemplateEnvironmentVariable $environmentVariable) use ($template, &$validationRules) {
                    $validationRules['env.' . $template->id . '.' . $environmentVariable->key] = ['required', 'string', 'max:255'];
                });

                $validator = Validator::make($request->toArray(), $validationRules);

                if ($validator->fails()) {
                    $cluster->delete();

                    return Response::generate(400, 'error', 'Validation failed', $validator->errors());
                }

                $requestFields = (object) (array_key_exists($request->template_id, $request->data ?? []) ? $request->data[$request->template_id] : []);

                $template->fields->each(function (TemplateField $field) use ($requestFields, $cluster) {
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
                        ClusterSecretData::create([
                            'cluster_id'        => $cluster->id,
                            'template_field_id' => $field->id,
                            'key'               => $field->key,
                            'value'             => $value,
                        ]);
                    } else {
                        ClusterData::create([
                            'cluster_id'        => $cluster->id,
                            'template_field_id' => $field->id,
                            'key'               => $field->key,
                            'value'             => $value,
                        ]);
                    }
                });

                $requestEnvs = (object) (array_key_exists($request->template_id, $request->env ?? []) ? $request->env[$request->template_id] : []);

                $template->environmentVariables->each(function (TemplateEnvironmentVariable $environmentVariable) use ($requestEnvs, $cluster) {
                    ClusterEnvironmentVariable::create([
                        'cluster_id'               => $cluster->id,
                        'template_env_variable_id' => $environmentVariable->id,
                        'value'                    => $requestEnvs->{$environmentVariable->key},
                    ]);
                });
            }

            GitCredential::create([
                'cluster_id'  => $cluster->id,
                'url'         => $request->git['url'],
                'branch'      => $request->git['branch'],
                'credentials' => $request->git['credentials'],
                'username'    => $request->git['username'],
                'email'       => $request->git['email'],
                'base_path'   => $request->git['base_path'],
            ]);

            K8sCredential::create([
                'cluster_id'            => $cluster->id,
                'api_url'               => $request->k8s['api_url'],
                'service_account_token' => $request->k8s['service_account_token'],
                'node_prefix'           => $request->k8s['node_prefix'],
                ...(empty($cluster->template) ? [
                    'kubeconfig' => $request->k8s['kubeconfig'],
                ] : [
                    'kubeconfig' => '',
                ]),
            ]);

            Ns::create([
                'cluster_id' => $cluster->id,
                'name'       => $request->namespace['utility'],
                'type'       => Ns::TYPE_UTILITY,
            ]);

            Ns::create([
                'cluster_id' => $cluster->id,
                'name'       => $request->namespace['ingress'],
                'type'       => Ns::TYPE_INGRESS,
            ]);

            Resource::create([
                'cluster_id' => $cluster->id,
                'type'       => Resource::TYPE_ALERT,
                'cpu'        => $request->resources['alert']['cpu'],
                'memory'     => $request->resources['alert']['memory'],
                'storage'    => $request->resources['alert']['storage'],
                'pods'       => $request->resources['alert']['pods'],
            ]);

            Resource::create([
                'cluster_id' => $cluster->id,
                'type'       => Resource::TYPE_LIMIT,
                'cpu'        => $request->resources['limit']['cpu'],
                'memory'     => $request->resources['limit']['memory'],
                'storage'    => $request->resources['limit']['storage'],
                'pods'       => $request->resources['limit']['pods'],
            ]);

            return Response::generate(201, 'success', 'Cluster created successfully', [
                'cluster' => $cluster->toArray(),
            ]);
        }

        return Response::generate(500, 'error', 'Cluster not created');
    }

    /**
     * Update the cluster.
     *
     * @OA\Patch(
     *     path="/api/projects/{project_id}/clusters/{cluster_id}",
     *     summary="Update a cluster",
     *     tags={"Clusters"},
     *
     *     @OA\Parameter(ref="#/components/parameters/project_id"),
     *     @OA\Parameter(ref="#/components/parameters/cluster_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="git", type="object",
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="branch", type="string"),
     *                 @OA\Property(property="credentials", type="string"),
     *                 @OA\Property(property="username", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="base_path", type="string"),
     *             ),
     *             @OA\Property(property="k8s", type="object",
     *                 @OA\Property(property="api_url", type="string"),
     *                 @OA\Property(property="kubeconfig", type="string"),
     *                 @OA\Property(property="service_account_token", type="string"),
     *                 @OA\Property(property="node_prefix", type="string", nullable=true),
     *             ),
     *             @OA\Property(property="namespace", type="object",
     *                 @OA\Property(property="utility", type="string"),
     *                 @OA\Property(property="ingress", type="string"),
     *             ),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Cluster updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Cluster updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="cluster", ref="#/components/schemas/Cluster")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $project_id
     * @param string  $cluster_id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function action_update(string $project_id, string $cluster_id, Request $request)
    {
        $validator = Validator::make(array_merge(
            $request->all(),
            [
                'cluster_id' => $cluster_id,
            ]
        ), [
            'cluster_id'                => ['required', 'string', 'max:255'],
            'name'                      => ['required', 'string', 'max:255'],
            'git'                       => ['required', 'array'],
            'git.url'                   => ['required', 'string', 'max:255'],
            'git.branch'                => ['required', 'string', 'max:255'],
            'git.credentials'           => ['required', 'string'],
            'git.username'              => ['required', 'string', 'max:255'],
            'git.email'                 => ['required', 'email', 'max:255'],
            'git.base_path'             => ['required', 'string', 'max:255'],
            'k8s'                       => ['required', 'array'],
            'k8s.api_url'               => ['required', 'string', 'max:255'],
            'k8s.kubeconfig'            => ['required', 'string'],
            'k8s.service_account_token' => ['required', 'string'],
            'k8s.node_prefix'           => ['nullable', 'string', 'max:255'],
            'namespace'                 => ['required', 'array'],
            'namespace.utility'         => ['required', 'string', 'max:255'],
            'namespace.ingress'         => ['required', 'string', 'max:255'],
            'resources'                 => ['required', 'array'],
            'resources.alert'           => ['required', 'array'],
            'resources.alert.cpu'       => ['required', 'numeric'],
            'resources.alert.memory'    => ['required', 'numeric'],
            'resources.alert.storage'   => ['required', 'numeric'],
            'resources.alert.pods'      => ['required', 'numeric'],
            'resources.limit'           => ['required', 'array'],
            'resources.limit.cpu'       => ['required', 'numeric'],
            'resources.limit.memory'    => ['required', 'numeric'],
            'resources.limit.storage'   => ['required', 'numeric'],
            'resources.limit.pods'      => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if ($cluster = Cluster::where('id', $cluster_id)->first()) {
            if (! empty($cluster->template)) {
                $validationRules = [];

                $cluster->template->fields->each(function (TemplateField $field) use ($cluster, &$validationRules) {
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

                    $validationRules['data.' . $cluster->template->id . '.' . $field->key] = $rules;
                });

                $cluster->template->environmentVariables->each(function (TemplateEnvironmentVariable $environmentVariable) use ($cluster, &$validationRules) {
                    $validationRules['env.' . $cluster->template->id . '.' . $environmentVariable->key] = ['required', 'string', 'max:255'];
                });

                $validator = Validator::make($request->toArray(), $validationRules);

                if ($validator->fails()) {
                    return Response::generate(400, 'error', 'Validation failed', $validator->errors());
                }

                $requestFields = (object) (array_key_exists($cluster->template->id, $request->data ?? []) ? $request->data[$cluster->template->id] : []);

                $cluster->template->fields->each(function (TemplateField $field) use ($requestFields, $cluster) {
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
                        $cluster->clusterSecretData->where('template_field_id', '=', $field->id)->each(function (ClusterSecretData $clusterSecretData) use ($value) {
                            $clusterSecretData->update([
                                'value' => $value,
                            ]);
                        });
                    } else {
                        $cluster->clusterData->where('template_field_id', '=', $field->id)->each(function (ClusterData $clusterData) use ($value) {
                            $clusterData->update([
                                'value' => $value,
                            ]);
                        });
                    }
                });

                $requestEnvs = (object) (array_key_exists($cluster->template->id, $request->env ?? []) ? $request->env[$cluster->template->id] : []);

                $cluster->template->environmentVariables->each(function (TemplateEnvironmentVariable $environmentVariable) use ($requestEnvs, $cluster) {
                    $cluster->environmentVariables->where('template_env_variable_id', '=', $environmentVariable->id)->each(function (ClusterEnvironmentVariable $clusterEnvironmentVariable) use ($requestEnvs, $environmentVariable) {
                        $clusterEnvironmentVariable->update([
                            'value' => $requestEnvs->{$environmentVariable->key},
                        ]);
                    });
                });
            } else {
                Validator::make($request->all(), [
                    'k8s.kubeconfig' => ['required', 'string'],
                ])->validate();
            }

            if ($cluster->gitCredentials) {
                $cluster->gitCredentials->update([
                    'url'         => $request->git['url'],
                    'branch'      => $request->git['branch'],
                    'credentials' => $request->git['credentials'],
                    'username'    => $request->git['username'],
                    'email'       => $request->git['email'],
                    'base_path'   => $request->git['base_path'],
                ]);
            } else {
                GitCredential::create([
                    'cluster_id'  => $cluster->id,
                    'url'         => $request->git['url'],
                    'branch'      => $request->git['branch'],
                    'credentials' => $request->git['credentials'],
                    'username'    => $request->git['username'],
                    'email'       => $request->git['email'],
                    'base_path'   => $request->git['base_path'],
                ]);
            }

            if ($cluster->k8sCredentials) {
                $cluster->k8sCredentials->update([
                    'api_url'               => $request->k8s['api_url'],
                    'service_account_token' => $request->k8s['service_account_token'],
                    'node_prefix'           => $request->k8s['node_prefix'],
                    ...(empty($cluster->template) ? [
                        'kubeconfig' => $request->k8s['kubeconfig'],
                    ] : [
                        'kubeconfig' => '',
                    ]),
                ]);
            } else {
                K8sCredential::create([
                    'cluster_id'            => $cluster->id,
                    'api_url'               => $request->k8s['api_url'],
                    'service_account_token' => $request->k8s['service_account_token'],
                    'node_prefix'           => $request->k8s['node_prefix'],
                    ...(empty($cluster->template) ? [
                        'kubeconfig' => $request->k8s['kubeconfig'],
                    ] : [
                        'kubeconfig' => '',
                    ]),
                ]);
            }

            if ($cluster->utilityNamespace) {
                $cluster->utilityNamespace->update([
                    'name' => $request->namespace['utility'],
                ]);
            } else {
                Ns::create([
                    'cluster_id' => $cluster->id,
                    'name'       => $request->namespace['utility'],
                    'type'       => Ns::TYPE_UTILITY,
                ]);
            }

            if ($cluster->ingressNamespace) {
                $cluster->ingressNamespace->update([
                    'name' => $request->namespace['ingress'],
                ]);
            } else {
                Ns::create([
                    'cluster_id' => $cluster->id,
                    'name'       => $request->namespace['ingress'],
                    'type'       => Ns::TYPE_INGRESS,
                ]);
            }

            if ($cluster->alert) {
                $cluster->alert->update([
                    'cpu'     => $request->resources['alert']['cpu'],
                    'memory'  => $request->resources['alert']['memory'],
                    'storage' => $request->resources['alert']['storage'],
                    'pods'    => $request->resources['alert']['pods'],
                ]);
            } else {
                Resource::create([
                    'cluster_id' => $cluster->id,
                    'type'       => Resource::TYPE_ALERT,
                    'cpu'        => $request->resources['alert']['cpu'],
                    'memory'     => $request->resources['alert']['memory'],
                    'storage'    => $request->resources['alert']['storage'],
                    'pods'       => $request->resources['alert']['pods'],
                ]);
            }

            if ($cluster->limit) {
                $cluster->limit->update([
                    'cpu'     => $request->resources['limit']['cpu'],
                    'memory'  => $request->resources['limit']['memory'],
                    'storage' => $request->resources['limit']['storage'],
                    'pods'    => $request->resources['limit']['pods'],
                ]);
            } else {
                Resource::create([
                    'cluster_id' => $cluster->id,
                    'type'       => Resource::TYPE_LIMIT,
                    'cpu'        => $request->resources['limit']['cpu'],
                    'memory'     => $request->resources['limit']['memory'],
                    'storage'    => $request->resources['limit']['storage'],
                    'pods'       => $request->resources['limit']['pods'],
                ]);
            }

            $cluster->update([
                'name' => $request->name,
                ...($cluster->deployed_at ? [
                    'update'      => true,
                    'approved_at' => null,
                ] : []),
            ]);

            return Response::generate(200, 'success', 'Cluster updated successfully', [
                'cluster' => $cluster->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'Cluster not found');
    }

    /**
     * Delete the cluster.
     *
     * @OA\Delete(
     *     path="/api/projects/{project_id}/clusters/{cluster_id}",
     *     summary="Delete a cluster",
     *     tags={"Clusters"},
     *
     *     @OA\Parameter(ref="#/components/parameters/project_id"),
     *     @OA\Parameter(ref="#/components/parameters/cluster_id"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Cluster deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Cluster deleted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="cluster", ref="#/components/schemas/Cluster")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $project_id
     * @param string $cluster_id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function action_delete(string $project_id, string $cluster_id)
    {
        $validator = Validator::make([
            'cluster_id' => $cluster_id,
        ], [
            'cluster_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if ($cluster = Cluster::where('id', $cluster_id)->first()) {
            $cluster->delete();

            return Response::generate(200, 'success', 'Cluster deleted successfully', [
                'cluster' => $cluster->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'Cluster not found');
    }

    /**
     * Approve the deployment.
     *
     * @OA\Patch(
     *     path="/projects/{project_id}/clusters/{cluster_id}/approve",
     *     summary="Approve a cluster",
     *     tags={"Clusters"},
     *
     *     @OA\Parameter(ref="#/components/parameters/project_id"),
     *     @OA\Parameter(ref="#/components/parameters/cluster_id"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Cluster approved",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Cluster approved"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="cluster", ref="#/components/schemas/Cluster")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFoundResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $project_id
     * @param string $cluster_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_approve(string $project_id, string $cluster_id)
    {
        $validator = Validator::make([
            'project_id' => $project_id,
            'cluster_id' => $cluster_id,
        ], [
            'project_id' => ['required', 'string', 'max:255'],
            'cluster_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $cluster = Cluster::where('id', '=', $cluster_id)
            ->whereNotNull('template_id')
            ->first();

        if (empty($cluster)) {
            return Response::generate(404, 'error', 'Cluster not found');
        }

        if ($cluster->approved_at) {
            return Response::generate(400, 'error', 'Cluster already approved');
        }

        $cluster->update([
            'approved_at' => Carbon::now(),
        ]);

        return Response::generate(200, 'success', 'Cluster approved', [
            'cluster' => $cluster->toArray(),
        ]);
    }
}

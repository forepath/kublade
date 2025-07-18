<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Kubernetes\Clusters\Cluster;
use App\Models\Kubernetes\Clusters\GitCredential;
use App\Models\Kubernetes\Clusters\K8sCredential;
use App\Models\Kubernetes\Clusters\Ns;
use App\Models\Kubernetes\Clusters\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Class ClusterController.
 *
 * This class is the controller for the cluster actions.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class ClusterController extends Controller
{
    /**
     * Show the cluster dashboard.
     *
     * @param string $project_id
     * @param string $cluster_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function page_index(string $project_id, string $cluster_id = null)
    {
        return view('cluster.index', [
            'clusters' => Cluster::where('project_id', '=', $project_id)->paginate(10),
            'cluster'  => $cluster_id ? Cluster::where('id', $cluster_id)->first() : null,
        ]);
    }

    /**
     * Show the cluster add page.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function page_add()
    {
        return view('cluster.add');
    }

    /**
     * Add a new cluster.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function action_add(Request $request)
    {
        Validator::make($request->all(), [
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
        ])->validate();

        if (
            $cluster = Cluster::create([
                'project_id' => $request->project_id,
                'user_id'    => Auth::user()->id,
                'name'       => $request->name,
            ])
        ) {
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
                'kubeconfig'            => $request->k8s['kubeconfig'],
                'service_account_token' => $request->k8s['service_account_token'],
                'node_prefix'           => $request->k8s['node_prefix'],
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

            return redirect()->route('cluster.index', ['project_id' => $request->project_id])->with('success', __('Cluster created successfully.'));
        }

        return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
    }

    /**
     * Show the cluster update page.
     *
     * @param string $project_id
     * @param string $cluster_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function page_update(string $project_id, string $cluster_id)
    {
        if ($cluster = Cluster::where('id', $cluster_id)->first()) {
            return view('cluster.update', ['cluster' => $cluster]);
        }

        return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
    }

    /**
     * Update the cluster.
     *
     * @param string  $project_id
     * @param string  $cluster_id
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function action_update(string $project_id, string $cluster_id, Request $request)
    {
        Validator::make(array_merge(
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
        ])->validate();

        if ($cluster = Cluster::where('id', $cluster_id)->first()) {
            $cluster->update([
                'name' => $request->name,
            ]);

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
                    'kubeconfig'            => $request->k8s['kubeconfig'],
                    'service_account_token' => $request->k8s['service_account_token'],
                    'node_prefix'           => $request->k8s['node_prefix'],
                ]);
            } else {
                K8sCredential::create([
                    'cluster_id'            => $cluster->id,
                    'api_url'               => $request->k8s['api_url'],
                    'kubeconfig'            => $request->k8s['kubeconfig'],
                    'service_account_token' => $request->k8s['service_account_token'],
                    'node_prefix'           => $request->k8s['node_prefix'],
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

            return redirect()->route('cluster.index', ['project_id' => $project_id])->with('success', __('Cluster updated successfully.'));
        }

        return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
    }

    /**
     * Delete the cluster.
     *
     * @param string $project_id
     * @param string $cluster_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function action_delete(string $project_id, string $cluster_id)
    {
        Validator::make([
            'project_id' => $project_id,
            'cluster_id' => $cluster_id,
        ], [
            'project_id' => ['required', 'string', 'max:255'],
            'cluster_id' => ['required', 'string', 'max:255'],
        ])->validate();

        if ($cluster = Cluster::where('id', $cluster_id)->first()) {
            $cluster->delete();

            return redirect()->route('cluster.index', ['project_id' => $project_id])->with('success', __('Cluster deleted successfully.'));
        }

        return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
    }
}

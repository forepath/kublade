<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Helpers\Kubernetes\HelmManifests;
use App\Http\Controllers\Controller;
use App\Models\Projects\Deployments\Deployment;
use App\Models\Projects\Templates\Template;
use App\Models\Projects\Templates\TemplateDirectory;
use App\Models\Projects\Templates\TemplateEnvironmentVariable;
use App\Models\Projects\Templates\TemplateField;
use App\Models\Projects\Templates\TemplateFieldOption;
use App\Models\Projects\Templates\TemplateFile;
use App\Models\Projects\Templates\TemplateGitCredential;
use App\Models\Projects\Templates\TemplatePort;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

/**
 * Class TemplateController.
 *
 * This class is the controller for the template actions.
 *
 * @OA\Tag(
 *     name="Templates",
 *     description="Endpoints for template management"
 * )
 *
 * @OA\Parameter(
 *     name="template_id",
 *     in="path",
 *     required=true,
 *     description="The ID of the template",
 *
 *     @OA\Schema(type="string")
 * )
 *
 * @OA\Parameter(
 *     name="folder_id",
 *     in="path",
 *     required=true,
 *     description="The ID of the folder",
 *
 *     @OA\Schema(type="string")
 * )
 *
 * @OA\Parameter(
 *     name="file_id",
 *     in="path",
 *     required=true,
 *     description="The ID of the file",
 *
 *     @OA\Schema(type="string")
 * )
 *
 * @OA\Parameter(
 *     name="field_id",
 *     in="path",
 *     required=true,
 *     description="The ID of the field",
 *
 *     @OA\Schema(type="string")
 * )
 *
 * @OA\Parameter(
 *     name="option_id",
 *     in="path",
 *     required=true,
 *     description="The ID of the option",
 *
 *     @OA\Schema(type="string")
 * )
 *
 * @OA\Parameter(
 *     name="port_id",
 *     in="path",
 *     required=true,
 *     description="The ID of the port",
 *
 *     @OA\Schema(type="string")
 * )
 *
 * @OA\Parameter(
 *     name="env_variable_id",
 *     in="path",
 *     required=true,
 *     description="The ID of the environment variable",
 *
 *     @OA\Schema(type="string")
 * )
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class TemplateController extends Controller
{
    /**
     * List the templates.
     *
     * @OA\Get(
     *     path="/api/templates",
     *     summary="List templates",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/cursor"),
     *     @OA\Parameter(ref="#/components/parameters/type"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Templates retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Templates retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="templates", type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/Template")
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
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_list(Request $request)
    {
        $templates = Template::where('type', $request->type ?? 'application')->cursorPaginate(10);

        return Response::generate(200, 'success', 'Templates retrieved successfully', [
            'templates' => collect($templates->items())->map(function ($item) {
                return $item->toArray();
            }),
            'links' => [
                'next' => $templates->nextCursor()?->encode(),
                'prev' => $templates->previousCursor()?->encode(),
            ],
        ]);
    }

    /**
     * Get the template.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}",
     *     summary="Get template",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Template retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="template", ref="#/components/schemas/Template")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFoundResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_get(string $template_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $template = Template::where('id', $template_id)->first();

        if (!$template) {
            return Response::generate(404, 'error', 'Template not found');
        }

        return Response::generate(200, 'success', 'Template retrieved successfully', [
            'template' => $template->toArray(),
        ]);
    }

    /**
     * Add a new template.
     *
     * @OA\Post(
     *     path="/api/templates",
     *     summary="Add a new template",
     *     tags={"Templates"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="netpol", type="boolean", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Template added successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template added successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="template", ref="#/components/schemas/Template")
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
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_add(Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'type'   => ['required', 'string', 'in:application,cluster'],
            'name'   => ['required', 'string', 'max:255'],
            'netpol' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $template = Template::create([
                'user_id' => Auth::id(),
                'type'    => $request->type,
                'name'    => $request->name,
                'netpol'  => ! empty($request->netpol),
            ])
        ) {
            return Response::generate(200, 'success', 'Template added successfully', [
                'template' => $template->toArray(),
            ]);
        }

        return Response::generate(500, 'error', 'Template not created');
    }

    /**
     * Import a new template.
     *
     * @OA\Post(
     *     path="/api/templates/import",
     *     summary="Import a new template",
     *     tags={"Templates"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="netpol", type="boolean", nullable=true),
     *             @OA\Property(property="url", type="string"),
     *             @OA\Property(property="chart", type="string"),
     *             @OA\Property(property="repo", type="string", nullable=true),
     *             @OA\Property(property="namespace", type="string", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Template imported successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template imported successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="template", ref="#/components/schemas/Template")
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
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_import(Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'name'      => ['required', 'string', 'max:255'],
            'netpol'    => ['nullable', 'boolean'],
            'url'       => ['required', 'string'],
            'chart'     => ['required', 'string'],
            'repo'      => ['string', 'nullable'],
            'namespace' => ['string', 'nullable'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $template = Template::create([
                'user_id' => Auth::id(),
                'name'    => $request->name,
                'netpol'  => ! empty($request->netpol),
            ])
        ) {
            try {
                foreach (HelmManifests::generateManifests($request->url, $request->chart, $request->repo, $request->namespace) as $fileName => $fileContent) {
                    TemplateFile::create([
                        'template_id'           => $template->id,
                        'template_directory_id' => null,
                        'name'                  => $fileName,
                        'mime_type'             => 'text/yaml',
                        'content'               => $fileContent,
                    ]);
                }

                return Response::generate(200, 'success', 'Template imported successfully', [
                    'template' => $template->toArray(),
                ]);
            } catch (Exception $e) {
                return Response::generate(500, 'error', 'Template not imported');
            }
        }

        return Response::generate(500, 'error', 'Template not imported');
    }

    /**
     * Sync a template.
     *
     * @OA\Post(
     *     path="/api/templates/sync",
     *     summary="Sync a template",
     *     tags={"Templates"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="netpol", type="boolean", nullable=true),
     *             @OA\Property(property="git", type="object",
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="branch", type="string"),
     *                 @OA\Property(property="credentials", type="string"),
     *                 @OA\Property(property="username", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="base_path", type="string"),
     *             ),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Template syncing",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template syncing"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="template", ref="#/components/schemas/Template")
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
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_sync(Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'type'            => ['required', 'string', 'in:application,cluster'],
            'name'            => ['required', 'string', 'max:255'],
            'netpol'          => ['nullable', 'boolean'],
            'git'             => ['required', 'array'],
            'git.url'         => ['required', 'string', 'max:255'],
            'git.branch'      => ['required', 'string', 'max:255'],
            'git.credentials' => ['required', 'string'],
            'git.username'    => ['required', 'string', 'max:255'],
            'git.email'       => ['required', 'email', 'max:255'],
            'git.base_path'   => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $template = Template::create([
                'user_id' => Auth::id(),
                'type'    => $request->type,
                'name'    => $request->name,
                'netpol'  => ! empty($request->netpol),
            ])
        ) {
            try {
                TemplateGitCredential::create([
                    'template_id' => $template->id,
                    'url'         => $request->git['url'],
                    'branch'      => $request->git['branch'],
                    'credentials' => $request->git['credentials'],
                    'username'    => $request->git['username'],
                    'email'       => $request->git['email'],
                    'base_path'   => $request->git['base_path'],
                ]);

                return Response::generate(200, 'success', 'Template syncing', [
                    'template' => $template->toArray(),
                ]);
            } catch (Exception $e) {
                return Response::generate(500, 'error', 'Template not added');
            }
        }

        return redirect()->back()->with('warning', __('Ooops, something went wrong.'));
    }

    /**
     * Update the template.
     *
     * @OA\Patch(
     *     path="/api/templates/{template_id}",
     *     summary="Update the template",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="netpol", type="boolean", nullable=true),
     *             @OA\Property(property="git", type="object", nullable=true,
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="branch", type="string"),
     *                 @OA\Property(property="credentials", type="string"),
     *                 @OA\Property(property="username", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="base_path", type="string"),
     *             ),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Template updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="template", ref="#/components/schemas/Template")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_update(string $template_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'name'   => ['required', 'string', 'max:255'],
            'netpol' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if ($request->git) {
            $validator = Validator::make($request->git, [
                'url'         => ['required', 'string', 'max:255'],
                'branch'      => ['required', 'string', 'max:255'],
                'credentials' => ['required', 'string'],
                'username'    => ['required', 'string', 'max:255'],
                'email'       => ['required', 'email', 'max:255'],
                'base_path'   => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return Response::generate(400, 'error', 'Validation failed', $validator->errors());
            }
        }

        if ($template = Template::where('id', $template_id)->first()) {
            $template->update([
                'name'   => $request->name,
                'netpol' => ! empty($request->netpol),
            ]);

            if ($request->git) {
                $template->gitCredentials()->updateOrCreate([
                    'template_id' => $template->id,
                ], [
                    'url'         => $request->git['url'],
                    'branch'      => $request->git['branch'],
                    'credentials' => $request->git['credentials'],
                    'username'    => $request->git['username'],
                    'email'       => $request->git['email'],
                    'base_path'   => $request->git['base_path'],
                ]);
            } else {
                $template->gitCredentials()->delete();
            }

            return Response::generate(200, 'success', 'Template updated successfully', [
                'template' => $template->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'Template not found');
    }

    /**
     * Delete the template.
     *
     * @OA\Delete(
     *     path="/api/templates/{template_id}",
     *     summary="Delete the template",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Template deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template deleted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="template", ref="#/components/schemas/Template")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_delete(string $template_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if ($template = Template::where('id', $template_id)->first()) {
            $template->gitCredentials()->delete();
            $template->delete();

            return Response::generate(200, 'success', 'Template deleted successfully', [
                'template' => $template->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'Template not found');
    }

    /**
     * List the folders.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/folders",
     *     summary="List the folders",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/cursor"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Folders retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Folders retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="folders", type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/TemplateDirectory")
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
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_list_folder(string $template_id)
    {
        $folders = TemplateDirectory::where('template_id', $template_id)->cursorPaginate(10);

        return Response::generate(200, 'success', 'Folders retrieved successfully', [
            'folders' => collect($folders->items())->map(function ($item) {
                return $item->toArray();
            }),
            'links' => [
                'next' => $folders->nextCursor()?->encode(),
                'prev' => $folders->previousCursor()?->encode(),
            ],
        ]);
    }

    /**
     * Get the folder.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/folders/{folder_id}",
     *     summary="Get the folder",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/folder_id"),
     *
     *     @OA\Response(response=200, description="Folder retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Folder retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="folder", ref="#/components/schemas/TemplateDirectory")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $folder_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_get_folder(string $template_id, string $folder_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
            'folder_id'   => $folder_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
            'folder_id'   => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $folder = TemplateDirectory::where('template_id', $template_id)->where('id', $folder_id)->first();

        if (!$folder) {
            return Response::generate(404, 'error', 'Folder not found');
        }

        return Response::generate(200, 'success', 'Folder retrieved successfully', [
            'folder' => $folder->toArray(),
        ]);
    }

    /**
     * Add a new folder.
     *
     * @OA\Post(
     *     path="/api/templates/{template_id}/folders",
     *     summary="Add a new folder",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="parent_id", type="string", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Folder added successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Folder added successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="folder", ref="#/components/schemas/TemplateDirectory")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_add_folder(string $template_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $folder = TemplateDirectory::create([
                'template_id' => $template_id,
                'parent_id'   => $request->parent_id,
                'name'        => $request->name,
            ])
        ) {
            return Response::generate(200, 'success', 'Folder added successfully', [
                'folder' => $folder->toArray(),
            ]);
        }

        return Response::generate(500, 'error', 'Folder not created');
    }

    /**
     * Update the folder.
     *
     * @OA\Patch(
     *     path="/api/templates/{template_id}/folders/{folder_id}",
     *     summary="Update the folder",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/folder_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="parent_id", type="string", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Folder updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Folder updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="folder", ref="#/components/schemas/TemplateDirectory")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param string  $folder_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_update_folder(string $template_id, string $folder_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $folder = TemplateDirectory::where('id', $folder_id)
                ->where('template_id', '=', $template_id)
                ->first()
        ) {
            $folder->update([
                'name'      => $request->name,
                'parent_id' => $request->parent_id,
            ]);

            return Response::generate(200, 'success', 'Folder updated successfully', [
                'folder' => $folder->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'Folder not found');
    }

    /**
     * Delete the folder.
     *
     * @OA\Delete(
     *     path="/api/templates/{template_id}/folders/{folder_id}",
     *     summary="Delete the folder",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/folder_id"),
     *
     *     @OA\Response(response=200, description="Folder deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Folder deleted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="folder", ref="#/components/schemas/TemplateDirectory")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $folder_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_delete_folder(string $template_id, string $folder_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
            'folder_id'   => $folder_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
            'folder_id'   => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $folder = TemplateDirectory::where('id', $folder_id)
                ->where('template_id', '=', $template_id)
                ->first()
        ) {
            $folder->delete();

            return Response::generate(200, 'success', 'Folder deleted successfully', [
                'folder' => $folder->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'Folder not found');
    }

    /**
     * List the files.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/files",
     *     summary="List the files",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/cursor"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Files retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Files retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="files", type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/TemplateFile")
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
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_list_file(string $template_id)
    {
        $files = TemplateFile::where('template_id', $template_id)->cursorPaginate(10);

        return Response::generate(200, 'success', 'Files retrieved successfully', [
            'files' => collect($files->items())->map(function ($item) {
                return $item->toArray();
            }),
            'links' => [
                'next' => $files->nextCursor()?->encode(),
                'prev' => $files->previousCursor()?->encode(),
            ],
        ]);
    }

    /**
     * Get the file.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/files/{file_id}",
     *     summary="Get the file",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/file_id"),
     *
     *     @OA\Response(response=200, description="File retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="File retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="file", ref="#/components/schemas/TemplateFile")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $file_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_get_file(string $template_id, string $file_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
            'file_id'     => $file_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
            'file_id'     => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $file = TemplateFile::where('template_id', $template_id)->where('id', $file_id)->first();

        if (!$file) {
            return Response::generate(404, 'error', 'File not found');
        }

        return Response::generate(200, 'success', 'File retrieved successfully', [
            'file' => $file->toArray(),
        ]);
    }

    /**
     * Add a new file.
     *
     * @OA\Post(
     *     path="/api/templates/{template_id}/files",
     *     summary="Add a new file",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="template_directory_id", type="string", nullable=true),
     *             @OA\Property(property="mime_type", type="string"),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="File added successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="File added successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="file", ref="#/components/schemas/TemplateFile")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_add_file(string $template_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'name'                  => ['required', 'string', 'max:255'],
            'template_directory_id' => ['nullable', 'string', 'max:255'],
            'mime_type'             => ['required', 'string', 'max:255'],
            'sort'                  => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $file = TemplateFile::create([
                'template_id'           => $template_id,
                'template_directory_id' => $request->template_directory_id,
                'name'                  => $request->name,
                'mime_type'             => $request->mime_type,
                'content'               => '',
                'sort'                  => $request->sort,
            ])
        ) {
            Deployment::where('delete', '=', false)
                ->whereNotNull('deployed_at')
                ->whereHas('template', function ($query) use ($template_id) {
                    $query->where('id', $template_id);
                })
                ->update([
                    'update'      => true,
                    'approved_at' => null,
                ]);

            return Response::generate(200, 'success', 'File added successfully', [
                'file' => $file->toArray(),
            ]);
        }

        return Response::generate(500, 'error', 'File not created');
    }

    /**
     * Update the file.
     *
     * @OA\Patch(
     *     path="/api/templates/{template_id}/files/{file_id}",
     *     summary="Update the file",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/file_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="template_directory_id", type="string", nullable=true),
     *             @OA\Property(property="mime_type", type="string"),
     *             @OA\Property(property="content", type="string", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="File updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="File updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="file", ref="#/components/schemas/TemplateFile")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param string  $file_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_update_file(string $template_id, string $file_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'name'                  => ['required', 'string', 'max:255'],
            'template_directory_id' => ['nullable', 'string', 'max:255'],
            'mime_type'             => ['required', 'string', 'max:255'],
            'content'               => ['nullable', 'string'],
            'sort'                  => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $file = TemplateFile::where('id', $file_id)
                ->where('template_id', '=', $template_id)
                ->first()
        ) {
            $file->update([
                'name'                  => $request->name,
                'template_directory_id' => $request->template_directory_id,
                'mime_type'             => $request->mime_type,
                ...($request->content ? ['content' => $request->content] : []),
                'sort' => $request->sort,
            ]);

            Deployment::where('delete', '=', false)
                ->whereNotNull('deployed_at')
                ->whereHas('template', function ($query) use ($template_id) {
                    $query->where('id', $template_id);
                })
                ->update([
                    'update'      => true,
                    'approved_at' => null,
                ]);

            return Response::generate(200, 'success', 'File updated successfully', [
                'file' => $file->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'File not found');
    }

    /**
     * Delete the file.
     *
     * @OA\Delete(
     *     path="/api/templates/{template_id}/files/{file_id}",
     *     summary="Delete the file",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/file_id"),
     *
     *     @OA\Response(response=200, description="File deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="File deleted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="file", ref="#/components/schemas/TemplateFile")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $file_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_delete_file(string $template_id, string $file_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
            'file_id'     => $file_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
            'file_id'     => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $file = TemplateFile::where('id', $file_id)
                ->where('template_id', '=', $template_id)
                ->first()
        ) {
            $file->delete();

            Deployment::where('delete', '=', false)
                ->whereNotNull('deployed_at')
                ->whereHas('template', function ($query) use ($template_id) {
                    $query->where('id', $template_id);
                })
                ->update([
                    'update'      => true,
                    'approved_at' => null,
                ]);

            return Response::generate(200, 'success', 'File deleted successfully', [
                'file' => $file->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'File not found');
    }

    /**
     * List the fields.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/fields",
     *     summary="List the fields",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/cursor"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Fields retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Fields retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="fields", type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/TemplateField")
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
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_list_field(string $template_id)
    {
        $fields = TemplateField::where('template_id', $template_id)->cursorPaginate(10);

        return Response::generate(200, 'success', 'Fields retrieved successfully', [
            'fields' => collect($fields->items())->map(function ($item) {
                return $item->toArray();
            }),
        ]);
    }

    /**
     * Get the field.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/fields/{field_id}",
     *     summary="Get the field",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/field_id"),
     *
     *     @OA\Response(response=200, description="Field retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Field retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="field", ref="#/components/schemas/TemplateField")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $field_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_get_field(string $template_id, string $field_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
            'field_id'    => $field_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
            'field_id'    => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $field = TemplateField::where('id', $field_id)
            ->where('template_id', '=', $template_id)
            ->first();

        if (!$field) {
            return Response::generate(404, 'error', 'Field not found');
        }

        return Response::generate(200, 'success', 'Field retrieved successfully', [
            'field' => $field->toArray(),
        ]);
    }

    /**
     * Add a new field.
     *
     * @OA\Post(
     *     path="/api/templates/{template_id}/fields",
     *     summary="Add a new field",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="label", type="string"),
     *             @OA\Property(property="key", type="string"),
     *             @OA\Property(property="value", type="string", nullable=true),
     *             @OA\Property(property="required", type="boolean", nullable=true),
     *             @OA\Property(property="secret", type="boolean", nullable=true),
     *             @OA\Property(property="set_on_create", type="boolean", nullable=true),
     *             @OA\Property(property="set_on_update", type="boolean", nullable=true),
     *             @OA\Property(property="min", type="number", nullable=true),
     *             @OA\Property(property="max", type="number", nullable=true),
     *             @OA\Property(property="step", type="number", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Field added successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Field added successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="field", ref="#/components/schemas/TemplateField")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_add_field(string $template_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'template_id'   => ['required', 'string'],
            'type'          => ['required', 'string'],
            'label'         => ['required', 'string'],
            'key'           => ['required', 'string'],
            'value'         => ['nullable', 'string'],
            'advanced'      => ['nullable', 'boolean'],
            'required'      => ['nullable', 'boolean'],
            'secret'        => ['nullable', 'boolean'],
            'set_on_create' => ['nullable', 'boolean'],
            'set_on_update' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        switch ($request->type) {
            case 'input_number':
            case 'input_range':
                $validator = Validator::make($request->toArray(), [
                    'min'  => ['required', 'numeric'],
                    'max'  => ['required', 'numeric'],
                    'step' => ['required', 'numeric'],
                ]);

                if ($validator->fails()) {
                    return Response::generate(400, 'error', 'Validation failed', $validator->errors());
                }

                $field = TemplateField::create([
                    'template_id'   => $request->template_id,
                    'type'          => $request->type,
                    'label'         => $request->label,
                    'key'           => $request->key,
                    'value'         => $request->value,
                    'min'           => $request->min,
                    'max'           => $request->max,
                    'step'          => $request->step,
                    'advanced'      => ! empty($request->advanced),
                    'required'      => ! empty($request->required),
                    'secret'        => ! empty($request->secret),
                    'set_on_create' => ! empty($request->set_on_create),
                    'set_on_update' => ! empty($request->set_on_update),
                ]);

                break;
            default:
                $field = TemplateField::create([
                    'template_id'   => $request->template_id,
                    'type'          => $request->type,
                    'label'         => $request->label,
                    'key'           => $request->key,
                    'value'         => $request->value,
                    'advanced'      => ! empty($request->advanced),
                    'required'      => ! empty($request->required),
                    'secret'        => ! empty($request->secret),
                    'set_on_create' => ! empty($request->set_on_create),
                    'set_on_update' => ! empty($request->set_on_update),
                ]);

                break;
        }

        if ($field) {
            return Response::generate(200, 'success', 'Field added successfully', [
                'field' => $field->toArray(),
            ]);
        }

        return Response::generate(500, 'error', 'Field not created');
    }

    /**
     * Update the field.
     *
     * @OA\Patch(
     *     path="/api/templates/{template_id}/fields/{field_id}",
     *     summary="Update the field",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/field_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="label", type="string"),
     *             @OA\Property(property="key", type="string"),
     *             @OA\Property(property="value", type="string", nullable=true),
     *             @OA\Property(property="required", type="boolean", nullable=true),
     *             @OA\Property(property="secret", type="boolean", nullable=true),
     *             @OA\Property(property="set_on_create", type="boolean", nullable=true),
     *             @OA\Property(property="set_on_update", type="boolean", nullable=true),
     *             @OA\Property(property="min", type="number", nullable=true),
     *             @OA\Property(property="max", type="number", nullable=true),
     *             @OA\Property(property="step", type="number", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Field updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Field updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="field", ref="#/components/schemas/TemplateField")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param string  $field_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_update_field(string $template_id, string $field_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'template_id'   => ['required', 'string'],
            'type'          => ['required', 'string'],
            'label'         => ['required', 'string'],
            'key'           => ['required', 'string'],
            'value'         => ['nullable', 'string'],
            'advanced'      => ['nullable', 'boolean'],
            'required'      => ['nullable', 'boolean'],
            'secret'        => ['nullable', 'boolean'],
            'set_on_create' => ['nullable', 'boolean'],
            'set_on_update' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $field = TemplateField::where('id', $field_id)
            ->where('template_id', '=', $template_id)
            ->first();

        if (empty($field)) {
            return Response::generate(404, 'error', 'Field not found');
        }

        switch ($request->type) {
            case 'input_number':
            case 'input_range':
                $validator = Validator::make($request->toArray(), [
                    'min'  => ['required', 'numeric'],
                    'max'  => ['required', 'numeric'],
                    'step' => ['required', 'numeric'],
                ]);

                if ($validator->fails()) {
                    return Response::generate(400, 'error', 'Validation failed', $validator->errors());
                }

                $field->update([
                    'template_id'   => $request->template_id,
                    'type'          => $request->type,
                    'label'         => $request->label,
                    'key'           => $request->key,
                    'value'         => $request->value,
                    'min'           => $request->min,
                    'max'           => $request->max,
                    'step'          => $request->step,
                    'advanced'      => ! empty($request->advanced),
                    'required'      => ! empty($request->required),
                    'secret'        => ! empty($request->secret),
                    'set_on_create' => ! empty($request->set_on_create),
                    'set_on_update' => ! empty($request->set_on_update),
                ]);

                break;
            default:
                $field->update([
                    'template_id'   => $request->template_id,
                    'type'          => $request->type,
                    'label'         => $request->label,
                    'key'           => $request->key,
                    'value'         => $request->value,
                    'advanced'      => ! empty($request->advanced),
                    'required'      => ! empty($request->required),
                    'secret'        => ! empty($request->secret),
                    'set_on_create' => ! empty($request->set_on_create),
                    'set_on_update' => ! empty($request->set_on_update),
                ]);

                break;
        }

        return Response::generate(200, 'success', 'Field updated successfully', [
            'field' => $field->toArray(),
        ]);
    }

    /**
     * Delete the field.
     *
     * @OA\Delete(
     *     path="/api/templates/{template_id}/fields/{field_id}",
     *     summary="Delete the field",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/field_id"),
     *
     *     @OA\Response(response=200, description="Field deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Field deleted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="field", type="object")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $field_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_delete_field(string $template_id, string $field_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
            'field_id'    => $field_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
            'field_id'    => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $field = TemplateField::where('id', $field_id)
                ->where('template_id', '=', $template_id)
                ->first()
        ) {
            $field->delete();

            return Response::generate(200, 'success', 'Field deleted successfully', [
                'field' => $field->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'Field not found');
    }

    /**
     * List the options.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/fields/{field_id}/options",
     *     summary="List the options",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/field_id"),
     *     @OA\Parameter(ref="#/components/parameters/cursor"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Options retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Options retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="options", type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/TemplateFieldOption")
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
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $field_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_list_option(string $template_id, string $field_id)
    {
        $options = TemplateFieldOption::where('template_field_id', $field_id)->cursorPaginate(10);

        return Response::generate(200, 'success', 'Options retrieved successfully', [
            'options' => collect($options->items())->map(function ($item) {
                return $item->toArray();
            }),
        ]);
    }

    /**
     * Get the option.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/fields/{field_id}/options/{option_id}",
     *     summary="Get the option",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/field_id"),
     *     @OA\Parameter(ref="#/components/parameters/option_id"),
     *
     *     @OA\Response(response=200, description="Option retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Option retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="option", ref="#/components/schemas/TemplateFieldOption")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $field_id
     * @param string $option_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_get_option(string $template_id, string $field_id, string $option_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
            'field_id'    => $field_id,
            'option_id'   => $option_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
            'field_id'    => ['required', 'string', 'max:255'],
            'option_id'   => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $option = TemplateFieldOption::where('id', $option_id)
            ->where('template_field_id', '=', $field_id)
            ->first();

        if (!$option) {
            return Response::generate(404, 'error', 'Option not found');
        }

        return Response::generate(200, 'success', 'Option retrieved successfully', [
            'option' => $option->toArray(),
        ]);
    }

    /**
     * Add a new option.
     *
     * @OA\Post(
     *     path="/api/templates/{template_id}/fields/{field_id}/options",
     *     summary="Add a new option",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/field_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="label", type="string"),
     *             @OA\Property(property="value", type="string"),
     *             @OA\Property(property="default", type="boolean", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Option added successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Option added successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="option", ref="#/components/schemas/TemplateFieldOption")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param string  $field_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_add_option(string $template_id, string $field_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'template_field_id' => ['required', 'string'],
            'label'             => ['required', 'string'],
            'value'             => ['required', 'string'],
            'default'           => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $option = TemplateFieldOption::create([
                'template_field_id' => $request->template_field_id,
                'label'             => $request->label,
                'value'             => $request->value,
                'default'           => ! empty($request->default),
            ])
        ) {
            return Response::generate(200, 'success', 'Option added successfully', [
                'option' => $option->toArray(),
            ]);
        }

        return Response::generate(500, 'error', 'Option not created');
    }

    /**
     * Update the option.
     *
     * @OA\Patch(
     *     path="/api/templates/{template_id}/fields/{field_id}/options/{option_id}",
     *     summary="Update the option",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/field_id"),
     *     @OA\Parameter(ref="#/components/parameters/option_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="label", type="string"),
     *             @OA\Property(property="value", type="string"),
     *             @OA\Property(property="default", type="boolean", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Option updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Option updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="option", ref="#/components/schemas/TemplateFieldOption")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param string  $field_id
     * @param string  $option_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_update_option(string $template_id, string $field_id, string $option_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'option_id' => ['required', 'string'],
            'label'     => ['required', 'string'],
            'value'     => ['required', 'string'],
            'default'   => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $option = TemplateFieldOption::where('id', $request->option_id)->first();

        if (empty($option)) {
            return Response::generate(404, 'error', 'Option not found');
        }

        $option->update([
            'label'   => $request->label,
            'value'   => $request->value,
            'default' => ! empty($request->default),
        ]);

        return Response::generate(200, 'success', 'Option updated successfully', [
            'option' => $option->toArray(),
        ]);
    }

    /**
     * Delete the option.
     *
     * @OA\Delete(
     *     path="/api/templates/{template_id}/fields/{field_id}/options/{option_id}",
     *     summary="Delete the option",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/field_id"),
     *     @OA\Parameter(ref="#/components/parameters/option_id"),
     *
     *     @OA\Response(response=200, description="Option deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Option deleted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="option", ref="#/components/schemas/TemplateFieldOption")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $field_id
     * @param string $option_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_delete_option(string $template_id, string $field_id, string $option_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
            'field_id'    => $field_id,
            'option_id'   => $option_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
            'field_id'    => ['required', 'string', 'max:255'],
            'option_id'   => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $option = TemplateFieldOption::where('id', $option_id)
                ->where('template_field_id', '=', $field_id)
                ->first()
        ) {
            $option->delete();

            return Response::generate(200, 'success', 'Option deleted successfully', [
                'option' => $option->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'Option not found');
    }

    /**
     * List the ports.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/ports",
     *     summary="List the ports",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/cursor"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Ports retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Ports retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="ports", type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/TemplatePort")
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
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_list_port(string $template_id)
    {
        $ports = TemplatePort::where('template_id', $template_id)->cursorPaginate(10);

        return Response::generate(200, 'success', 'Ports retrieved successfully', [
            'ports' => collect($ports->items())->map(function ($item) {
                return $item->toArray();
            }),
        ]);
    }

    /**
     * Get the port.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/ports/{port_id}",
     *     summary="Get the port",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/port_id"),
     *
     *     @OA\Response(response=200, description="Port retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Port retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="port", ref="#/components/schemas/TemplatePort")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $port_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_get_port(string $template_id, string $port_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
            'port_id'     => $port_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
            'port_id'     => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $port = TemplatePort::where('id', $port_id)
            ->where('template_id', '=', $template_id)
            ->first();

        if (!$port) {
            return Response::generate(404, 'error', 'Port not found');
        }

        return Response::generate(200, 'success', 'Port retrieved successfully', [
            'port' => $port->toArray(),
        ]);
    }

    /**
     * Add a new port.
     *
     * @OA\Post(
     *     path="/api/templates/{template_id}/ports",
     *     summary="Add a new port",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="group", type="string"),
     *             @OA\Property(property="claim", type="string", nullable=true),
     *             @OA\Property(property="preferred_port", type="number", nullable=true),
     *             @OA\Property(property="random", type="boolean", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Port added successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Port added successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="port", ref="#/components/schemas/TemplatePort")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_add_port(string $template_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'template_id'    => ['required', 'string'],
            'group'          => ['required', 'string'],
            'claim'          => ['nullable', 'string'],
            'preferred_port' => ['nullable', 'numeric'],
            'random'         => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $template = Template::where('id', $template_id)->first();

        if ($template->type == 'cluster') {
            return Response::generate(400, 'error', 'Cluster templates do not support ports');
        }

        if (
            $port = TemplatePort::create([
                'template_id'    => $template->id,
                'group'          => $request->group,
                'claim'          => $request->claim,
                'preferred_port' => $request->preferred_port,
                'random'         => $request->random,
            ])
        ) {
            return Response::generate(200, 'success', 'Port added successfully', [
                'port' => $port->toArray(),
            ]);
        }

        return Response::generate(500, 'error', 'Port not created');
    }

    /**
     * Update the port.
     *
     * @OA\Patch(
     *     path="/api/templates/{template_id}/ports/{port_id}",
     *     summary="Update the port",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/port_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="group", type="string"),
     *             @OA\Property(property="claim", type="string", nullable=true),
     *             @OA\Property(property="preferred_port", type="number", nullable=true),
     *             @OA\Property(property="random", type="boolean", nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Port updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Port updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="port", ref="#/components/schemas/TemplatePort")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param string  $port_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_update_port(string $template_id, string $port_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'template_id'    => ['required', 'string'],
            'group'          => ['required', 'string'],
            'claim'          => ['nullable', 'string'],
            'preferred_port' => ['nullable', 'numeric'],
            'random'         => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $port = TemplatePort::where('id', $port_id)->first();

        if (empty($port)) {
            return Response::generate(404, 'error', 'Port not found');
        }

        $port->update([
            'template_id'    => $request->template_id,
            'group'          => $request->group,
            'claim'          => $request->claim,
            'preferred_port' => $request->preferred_port,
            'random'         => ! empty($request->random),
        ]);

        return Response::generate(200, 'success', 'Port updated successfully', [
            'port' => $port->toArray(),
        ]);
    }

    /**
     * Delete the port.
     *
     * @OA\Delete(
     *     path="/api/templates/{template_id}/ports/{port_id}",
     *     summary="Delete the port",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/port_id"),
     *
     *     @OA\Response(response=200, description="Port deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Port deleted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="port", ref="#/components/schemas/TemplatePort")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $port_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_delete_port(string $template_id, string $port_id)
    {
        $validator = Validator::make([
            'template_id' => $template_id,
            'port_id'     => $port_id,
        ], [
            'template_id' => ['required', 'string', 'max:255'],
            'port_id'     => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $port = TemplatePort::where('id', $port_id)->first()
        ) {
            $port->delete();

            return Response::generate(200, 'success', 'Port deleted successfully', [
                'port' => $port->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'Port not found');
    }

    /**
     * List the environment variables.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/env-variables",
     *     summary="List the environment variables",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/cursor"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Environment variables retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Environment variables retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="env_variables", type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/TemplateEnvironmentVariable")
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
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_list_env_variable(string $template_id)
    {
        $env_variables = TemplateEnvironmentVariable::where('template_id', $template_id)->cursorPaginate(10);

        return Response::generate(200, 'success', 'Environment variables retrieved successfully', [
            'env_variables' => collect($env_variables->items())->map(function ($item) {
                return $item->toArray();
            }),
        ]);
    }

    /**
     * Get the environment variable.
     *
     * @OA\Get(
     *     path="/api/templates/{template_id}/env-variables/{env_variable_id}",
     *     summary="Get the environment variable",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/env_variable_id"),
     *
     *     @OA\Response(response=200, description="Environment variable retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Environment variable retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="env_variable", ref="#/components/schemas/TemplateEnvironmentVariable")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $env_variable_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_get_env_variable(string $template_id, string $env_variable_id)
    {
        $validator = Validator::make([
            'template_id'     => $template_id,
            'env_variable_id' => $env_variable_id,
        ], [
            'template_id'     => ['required', 'string', 'max:255'],
            'env_variable_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $env_variable = TemplateEnvironmentVariable::where('id', $env_variable_id)
            ->where('template_id', '=', $template_id)
            ->first();

        if (!$env_variable) {
            return Response::generate(404, 'error', 'Environment variable not found');
        }

        return Response::generate(200, 'success', 'Environment variable retrieved successfully', [
            'env_variable' => $env_variable->toArray(),
        ]);
    }

    /**
     * Add a new environment variable.
     *
     * @OA\Post(
     *     path="/api/templates/{template_id}/env-variables",
     *     summary="Add a new environment variable",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="key", type="string"),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Environment variable added successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Environment variable added successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="env_variable", ref="#/components/schemas/TemplateEnvironmentVariable")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_add_env_variable(string $template_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'template_id' => ['required', 'string'],
            'key'         => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $template = Template::where('id', $template_id)->first();

        if ($template->type == 'application') {
            return Response::generate(400, 'error', 'Application templates do not support environment variables');
        }

        if (
            $env_variable = TemplateEnvironmentVariable::create([
                'template_id' => $template->id,
                'key'         => $request->key,
            ])
        ) {
            return Response::generate(200, 'success', 'Environment variable added successfully', [
                'env_variable' => $env_variable->toArray(),
            ]);
        }

        return Response::generate(500, 'error', 'Environment variable not created');
    }

    /**
     * Update the port.
     *
     * @OA\Patch(
     *     path="/api/templates/{template_id}/env-variables/{env_variable_id}",
     *     summary="Update the environment variable",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/port_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="key", type="string"),
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Environment variable updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Port updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="env_variable", ref="#/components/schemas/TemplateEnvironmentVariable")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string  $template_id
     * @param string  $env_variable_id
     * @param Request $request
     * @param string  $port_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_update_env_variable(string $template_id, string $env_variable_id, Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'template_id' => ['required', 'string'],
            'key'         => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        $env_variable = TemplateEnvironmentVariable::where('id', $env_variable_id)->first();

        if (empty($env_variable)) {
            return Response::generate(404, 'error', 'Environment variable not found');
        }

        $env_variable->update([
            'template_id' => $request->template_id,
            'key'         => $request->key,
        ]);

        return Response::generate(200, 'success', 'Environment variable updated successfully', [
            'env_variable' => $env_variable->toArray(),
        ]);
    }

    /**
     * Delete the port.
     *
     * @OA\Delete(
     *     path="/api/templates/{template_id}/env-variables/{env_variable_id}",
     *     summary="Delete the environment variable",
     *     tags={"Templates"},
     *
     *     @OA\Parameter(ref="#/components/parameters/template_id"),
     *     @OA\Parameter(ref="#/components/parameters/env_variable_id"),
     *
     *     @OA\Response(response=200, description="Environment variable deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Environment variable deleted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="env_variable", ref="#/components/schemas/TemplateEnvironmentVariable")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $template_id
     * @param string $env_variable_id
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function action_delete_env_variable(string $template_id, string $env_variable_id)
    {
        $validator = Validator::make([
            'template_id'     => $template_id,
            'env_variable_id' => $env_variable_id,
        ], [
            'template_id'     => ['required', 'string', 'max:255'],
            'env_variable_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $env_variable = TemplateEnvironmentVariable::where('id', $env_variable_id)->first()
        ) {
            $env_variable->delete();

            return Response::generate(200, 'success', 'Environment variable deleted successfully', [
                'env_variable' => $env_variable->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'Environment variable not found');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Helpers\API\Response;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Class UserController.
 *
 * This class is the controller for the user actions.
 *
 * @OA\Tag(
 *     name="Users",
 *     description="Endpoints for user management"
 * )
 *
 * @OA\Parameter(
 *     name="user_id",
 *     in="path",
 *     required=true,
 *     description="The ID of the user",
 *
 *     @OA\Schema(type="string")
 * )
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class UserController extends Controller
{
    /**
     * List the users.
     *
     * @OA\Get(
     *     path="/api/users",
     *     summary="List users",
     *     tags={"Users"},
     *
     *     @OA\Parameter(ref="#/components/parameters/cursor"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="users", type="array",
     *
     *                     @OA\Items(type="object")
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function action_list()
    {
        $users = User::cursorPaginate(10);

        return Response::generate(200, 'success', 'Users retrieved successfully', [
            'users' => collect($users->items())->map(function ($item) {
                return $item->toArray();
            }),
            'links' => [
                'next' => $users->nextCursor()?->encode(),
                'prev' => $users->previousCursor()?->encode(),
            ],
        ]);
    }

    /**
     * Get the user.
     *
     * @OA\Get(
     *     path="/api/users/{user_id}",
     *     summary="Get a user",
     *     tags={"Users"},
     *
     *     @OA\Parameter(ref="#/components/parameters/user_id"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedResponse"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerErrorResponse")
     * )
     *
     * @param string $user_id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function action_get(string $user_id)
    {
        $validator = Validator::make([
            'user_id' => $user_id,
        ], [
            'user_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if ($user = User::where('id', $user_id)->first()) {
            return Response::generate(200, 'success', 'User retrieved successfully', [
                'user' => $user->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'User not found');
    }

    /**
     * Add a new user.
     *
     * @OA\Post(
     *     path="/api/users",
     *     summary="Add a new user",
     *     tags={"Users"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="password", type="string", nullable=true),
     *             @OA\Property(property="password_confirmation", type="string", nullable=true),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), nullable=true),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User")
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
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'    => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles'       => ['nullable', 'array'],
            'permissions' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password ?? Str::random(16)),
            ])
        ) {
            Artisan::call('kbl:permissions:sync');

            $user->syncRoles($request->roles ?? []);
            $user->syncPermissions($request->permissions ?? []);

            return Response::generate(200, 'success', 'User created successfully', [
                'user' => $user->toArray(),
            ]);
        }

        return Response::generate(500, 'error', 'User not created');
    }

    /**
     * Update the user.
     *
     * @OA\Patch(
     *     path="/api/users/{user_id}",
     *     summary="Update a user",
     *     tags={"Users"},
     *
     *     @OA\Parameter(ref="#/components/parameters/user_id"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="password", type="string", nullable=true),
     *             @OA\Property(property="password_confirmation", type="string", nullable=true),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), nullable=true),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), nullable=true),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User")
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
     * @param string  $user_id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function action_update(string $user_id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'    => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles'       => ['nullable', 'array'],
            'permissions' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if ($user = User::where('id', $user_id)->first()) {
            $user->update([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $request->password ? Hash::make($request->password) : $user->password,
            ]);

            Artisan::call('kbl:permissions:sync');

            $user->syncRoles($request->roles ?? []);
            $user->syncPermissions($request->permissions ?? []);

            return Response::generate(200, 'success', 'User updated successfully', [
                'user' => $user->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'User not found');
    }

    /**
     * Delete the user.
     *
     * @OA\Delete(
     *     path="/api/users/{user_id}",
     *     summary="Delete a user",
     *     tags={"Users"},
     *
     *     @OA\Parameter(ref="#/components/parameters/user_id"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User deleted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User")
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
     * @param string $user_id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function action_delete(string $user_id)
    {
        $validator = Validator::make([
            'user_id' => $user_id,
        ], [
            'user_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if (
            $user = User::where('id', $user_id)
                ->where('id', '!=', Auth::id())
                ->first()
        ) {
            $user->delete();

            return Response::generate(200, 'success', 'User deleted successfully', [
                'user' => $user->toArray(),
            ]);
        }

        return Response::generate(404, 'error', 'User not found');
    }

    /**
     * Generate a magic link for the user.
     *
     * @OA\Post(
     *     path="/api/users/{user_id}/magic-link",
     *     summary="Generate a magic link for the user",
     *     tags={"Users"},
     *
     *     @OA\Parameter(ref="#/components/parameters/user_id"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Magic link generated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Magic link generated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="link", type="string", example="https://example.com/auth/magic-link/1234567890"),
     *                 @OA\Property(property="expires_at", type="string", example="2025-01-01T00:00:00.000000Z")
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
     * @param string $user_id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function action_magic_link(string $user_id)
    {
        $validator = Validator::make([
            'user_id' => $user_id,
        ], [
            'user_id' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return Response::generate(400, 'error', 'Validation failed', $validator->errors());
        }

        if ($user = User::where('id', $user_id)->first()) {
            $expires_at = Carbon::now()->addMinutes(15);

            $link = URL::temporarySignedRoute(
                'auth.magic.token',
                $expires_at,
                ['token' => Crypt::encryptString($user->id)]
            );

            Cache::put('magic_link_' . $user->id, $link, $expires_at);

            return Response::generate(200, 'success', 'Magic link generated successfully', [
                'user'       => $user->toArray(),
                'link'       => $link,
                'expires_at' => $expires_at->toISOString(),
            ]);
        }

        return Response::generate(404, 'error', 'User not found');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ResourceInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Teacher and admin accounts (W10). Accounts are created by an admin; when no
 * password is given a temporary one is returned once and must be changed.
 *
 * @group Foydalanuvchilar
 */
class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with('courses.studyGroup', 'courses.subject')
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->input('role')))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('login', 'like', '%'.$request->string('q').'%')))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return UserResource::collection($users);
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $temporaryPassword = $request->filled('password') ? null : $this->temporaryPassword();

        $user = User::query()->create([
            ...$request->safe()->except(['password', 'blocked']),
            'password' => $request->input('password') ?? $temporaryPassword,
            'must_change_password' => $temporaryPassword !== null,
            'blocked_at' => $request->boolean('blocked') ? now() : null,
        ]);

        return UserResource::make($user)
            ->additional(['temporary_password' => $temporaryPassword])
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return UserResource::make($user->load('courses.studyGroup', 'courses.subject'));
    }

    /**
     * Blocking an account also signs it out of every device.
     */
    public function update(SaveUserRequest $request, User $user): UserResource
    {
        DB::transaction(function () use ($request, $user): void {
            $user->fill($request->safe()->except('blocked'));

            if ($request->has('blocked')) {
                $user->blocked_at = $request->boolean('blocked') ? ($user->blocked_at ?? now()) : null;
            }

            $user->save();

            if ($user->isBlocked()) {
                $user->tokens()->delete();
            }
        });

        return UserResource::make($user);
    }

    /**
     * Give the user a new temporary password and sign them out everywhere.
     */
    public function resetPassword(User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $temporaryPassword = $this->temporaryPassword();

        DB::transaction(function () use ($user, $temporaryPassword): void {
            $user->update(['password' => $temporaryPassword, 'must_change_password' => true]);
            $user->tokens()->delete();
        });

        return response()->json(['temporary_password' => $temporaryPassword]);
    }

    /**
     * Accounts that ran lessons are kept for the record; block them instead.
     */
    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);

        if ($user->lessons()->exists()) {
            throw ResourceInUseException::hasGrades('Foydalanuvchi', 'Uni bloklang.');
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    private function temporaryPassword(): string
    {
        return Str::password(10, symbols: false);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateOwnerAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOwnerRequest;
use App\Support\MailConfig;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OwnerController extends Controller
{
    public function store(StoreOwnerRequest $request, CreateOwnerAccount $createOwner): JsonResponse|Response
    {
        $data = $request->validated();
        $data['send_invitation'] = $request->boolean('send_invitation');
        $data['must_change_password'] = $request->boolean('must_change_password', true);

        $result = $createOwner->handle($data);
        $user = $result['user'];
        $invitation = $result['invitation'];
        $plainPassword = $result['plain_password'];

        $credentials = $plainPassword === null
            ? null
            : [
                'email' => $user->email,
                'plain_password' => $plainPassword,
            ];

        if ($request->expectsJson()) {
            return response()->json([
                'owner' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'credentials' => $credentials,
                'invitation' => $invitation,
            ], 201);
        }

        // Password is never flashed to session — only present in this one response.
        return Inertia::render('Admin/Users/Create', [
            'mailDeliverable' => MailConfig::isDeliverable(),
            'created' => [
                'email' => $user->email,
                'plain_password' => $plainPassword,
                'invitation' => $invitation,
            ],
        ]);
    }
}

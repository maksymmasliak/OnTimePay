<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class ClientController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Client::class, 'client');
    }

    public function index(): JsonResponse
    {
        $clients = Client::latest()->paginate(15);

        return response()->json($clients);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json($client);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return response()->json($client, 201);
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return response()->json($client);
    }

    public function destroy(Client $client): JsonResponse
    {
        if ($client->invoices()->exists()) {
            throw ValidationException::withMessages([
                'client' => ['Cannot delete a client that has invoices. Delete or reassign their invoices first.'],
            ]);
        }

        $client->delete();

        return response()->json(null, 204);
    }
}

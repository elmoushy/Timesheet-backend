<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class ClientController extends Controller
{
    /* ─────────────────────  Helpers  ───────────────────── */
    private function ok(string $msg, $data = [], int $code = 200): JsonResponse
    {
        return response()->json(['message' => $msg, 'data' => $data], $code);
    }

    private function fail(string $msg, int $code = 400): JsonResponse
    {
        return response()->json(['message' => $msg, 'data' => []], $code);
    }

    /* ─────────────────────  Core rules  ───────────────────── */
    private function clientRules(int $id = 0): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $id > 0 ? Rule::unique('xxx_clients', 'name')->ignore($id) : Rule::unique('xxx_clients', 'name'),
            ],
            'alias' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'business_sector' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'contact_numbers' => 'nullable|array',
            'contact_numbers.*.name' => 'required_with:contact_numbers|string|max:100',
            'contact_numbers.*.number' => 'required_with:contact_numbers|string|max:50',
            'contact_numbers.*.type' => 'nullable|string|in:client,oracle,private,other',
            'contact_numbers.*.is_primary' => 'boolean',
        ];
    }

    /* ─────────────────────  Index & List  ───────────────────── */
    public function index(Request $request): JsonResponse
    {
        $query = Client::with('contactNumbers');

        // Apply search filters if provided
        if ($request->has('search')) {
            $searchTerm = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                    ->orWhere('alias', 'LIKE', $searchTerm)
                    ->orWhere('business_sector', 'LIKE', $searchTerm);
            });
        }

        // Apply region filter if provided
        if ($request->has('region')) {
            $query->where('region', $request->input('region'));
        }

        return $this->ok(
            'Clients fetched successfully',
            $query->paginate($request->input('per_page', 10))
        );
    }

    public function list(): JsonResponse
    {
        // Return all clients for dropdown/select lists (no pagination)
        $clients = Client::with('contactNumbers')->select('id', 'name', 'alias')->orderBy('name')->get();

        return $this->ok('All clients fetched successfully', $clients);
    }

    /* ─────────────────────  Show  ───────────────────── */
    public function show(int $id): JsonResponse
    {
        $client = Client::with(['projects', 'contactNumbers'])->find($id);

        return $client
            ? $this->ok('Client fetched successfully', $client)
            : $this->fail('Client not found', 404);
    }

    /* ─────────────────────  Store  ───────────────────── */
    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), $this->clientRules());
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        try {
            $client = Client::create($request->only([
                'name', 'alias', 'region', 'address', 'business_sector', 'notes',
            ]));

            // Handle contact numbers if provided - these are client-specific contact numbers
            if ($request->has('contact_numbers') && is_array($request->contact_numbers)) {
                foreach ($request->contact_numbers as $numberData) {
                    $client->contactNumbers()->create([
                        'name' => $numberData['name'],
                        'number' => $numberData['number'],
                        'type' => $numberData['type'] ?? 'client',
                        'is_primary' => $numberData['is_primary'] ?? false,
                        'project_id' => null, // Ensure this is a client contact number, not project-specific
                    ]);
                }
            }

            // Refresh the client with contact numbers
            $client->load('contactNumbers');

            return $this->ok('Client created successfully', $client, 201);
        } catch (Throwable $e) {
            return $this->fail('Error creating client: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Update  ───────────────────── */
    public function update(Request $request, int $id): JsonResponse
    {
        $client = Client::find($id);
        if (! $client) {
            return $this->fail('Client not found', 404);
        }

        $v = Validator::make($request->all(), $this->clientRules($id));
        if ($v->fails()) {
            return $this->fail($v->errors()->first(), 422);
        }

        try {
            $client->update($request->only([
                'name', 'alias', 'region', 'address', 'business_sector', 'notes',
            ]));

            // Handle contact numbers if provided - these are client-specific contact numbers
            if ($request->has('contact_numbers')) {
                // Delete existing client contact numbers (only those without project_id)
                $client->contactNumbers()->whereNull('project_id')->delete();

                // Create new contact numbers for the client
                if (is_array($request->contact_numbers)) {
                    foreach ($request->contact_numbers as $numberData) {
                        $client->contactNumbers()->create([
                            'name' => $numberData['name'],
                            'number' => $numberData['number'],
                            'type' => $numberData['type'] ?? 'client',
                            'is_primary' => $numberData['is_primary'] ?? false,
                            'project_id' => null, // Ensure this is a client contact number, not project-specific
                        ]);
                    }
                }
            }

            // Refresh the client with contact numbers
            $client->load('contactNumbers');

            return $this->ok('Client updated successfully', $client);
        } catch (Throwable $e) {
            return $this->fail('Error updating client: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Delete & Bulk delete  ───────────────────── */
    public function destroy(int $id): JsonResponse
    {
        $client = Client::find($id);
        if (! $client) {
            return $this->fail('Client not found', 404);
        }

        try {
            // Check if client has associated projects
            if ($client->projects()->count() > 0) {
                return $this->fail('Cannot delete client with associated projects', 422);
            }

            // Delete associated contact numbers
            $client->contactNumbers()->delete();

            // Delete the client
            $client->delete();

            return $this->ok('Client deleted successfully');
        } catch (Throwable $e) {
            return $this->fail('Error deleting client: '.$e->getMessage(), 500);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids) || empty($ids)) {
            return $this->fail('ids must be a non-empty array', 422);
        }

        try {
            // Check if any clients have associated projects
            $clientsWithProjects = Client::whereIn('id', $ids)
                ->whereHas('projects')
                ->pluck('name')
                ->toArray();

            if (! empty($clientsWithProjects)) {
                return $this->fail(
                    'Cannot delete clients with associated projects: '.implode(', ', $clientsWithProjects),
                    422
                );
            }

            // Delete associated contact numbers
            ClientNumber::whereIn('client_id', $ids)->delete();

            // Delete the clients
            $deleted = Client::whereIn('id', $ids)->delete();

            return $this->ok($deleted
                ? "$deleted client(s) deleted successfully"
                : 'No clients were deleted'
            );
        } catch (Throwable $e) {
            return $this->fail('Error deleting clients: '.$e->getMessage(), 500);
        }
    }

    /* ─────────────────────  Delete Client Number  ───────────────────── */
    public function destroyClientNumber(int $id): JsonResponse
    {
        $clientNumber = ClientNumber::find($id);
        if (! $clientNumber) {
            return $this->fail('Client number not found', 404);
        }

        try {
            $clientNumber->delete();

            return $this->ok('Client number deleted successfully');
        } catch (Throwable $e) {
            return $this->fail('Error deleting client number: '.$e->getMessage(), 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\Demo\DemoState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Control remoto de la demo (celular → Mission Control del PC).
 * Desactivado salvo que DEMO_TOKEN esté definido; sin la clave correcta responde 404.
 */
class DemoController
{
    public function panel(Request $request)
    {
        $this->authorize($request);

        return view('demo.panel', [
            'key' => (string) $request->query('k'),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
            'state' => DemoState::get(),
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        $this->authorize($request);

        return response()->json(DemoState::get());
    }

    public function command(Request $request): JsonResponse
    {
        $this->authorize($request);

        $data = $request->validate([
            'ids' => ['array'],
            'ids.*' => ['integer'],
            'state' => ['nullable', Rule::in(DemoState::STATES)],
            'reset' => ['boolean'],
        ]);

        if ($data['reset'] ?? false) {
            return response()->json(DemoState::reset());
        }

        $ids = Site::query()->whereIn('id', $data['ids'] ?? [])->pluck('id')->all();

        return response()->json(DemoState::apply($ids, $data['state'] ?? null));
    }

    private function authorize(Request $request): void
    {
        $token = (string) config('mediadev.demo_token');
        $given = (string) ($request->query('k') ?? $request->input('k'));

        abort_if($token === '' || ! hash_equals($token, $given), 404);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\TransactionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionTypeController extends Controller
{
    public function index(): View
    {
        return view('types', [
            'types' => TransactionType::withCount('transactions')->orderBy('name')->get(),
            'colors' => TransactionType::COLORS,
        ]);
    }

    /**
     * Create a type — from the Types page (form post) or from the transaction
     * popup's quick add (JSON).
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $type = TransactionType::create($this->validated($request));

        if ($request->expectsJson()) {
            return response()->json($type->only(['id', 'name', 'color']), 201);
        }

        return redirect()->route('types.index')->with('success', "Type \"{$type->name}\" created.");
    }

    public function update(Request $request, TransactionType $type): RedirectResponse
    {
        $type->update($this->validated($request, $type));

        return redirect()->route('types.index')->with('success', "Type \"{$type->name}\" updated.");
    }

    public function destroy(TransactionType $type): RedirectResponse
    {
        // The foreign key sets these transactions back to "Not classified".
        $count = $type->transactions()->count();
        $type->delete();

        return redirect()->route('types.index')->with('success', "Type \"{$type->name}\" deleted"
            . ($count ? " — {$count} " . str('transaction')->plural($count) . ' are now unclassified.' : '.'));
    }

    private function validated(Request $request, ?TransactionType $type = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('transaction_types', 'name')->ignore($type)],
            'color' => ['required', Rule::in(array_keys(TransactionType::COLORS))],
        ], [
            'name.unique' => 'A type with this name already exists.',
        ]);
    }
}

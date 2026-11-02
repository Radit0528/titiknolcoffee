<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SupplierRequest;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('owner.suppliers.index', [
            'suppliers' => Supplier::query()
                ->withCount('ingredients')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('owner.suppliers.form', ['supplier' => new Supplier]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return redirect()->route('owner.suppliers.index')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('owner.suppliers.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('owner.suppliers.index')->with('success', 'Supplier berhasil diperbarui.');
    }

    /**
     * Soft delete; ingredients keep pointing at it so their history stays readable.
     */
    public function destroy(Supplier $supplier, AuditLogger $audit): RedirectResponse
    {
        $audit->log('supplier.deleted', $supplier, ['name' => $supplier->name]);
        $supplier->delete();

        return redirect()->route('owner.suppliers.index')->with('success', 'Supplier berhasil dihapus.');
    }
}

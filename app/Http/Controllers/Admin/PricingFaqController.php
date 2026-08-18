<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingFaq;
use Illuminate\Http\Request;

/**
 * Admin pricing-FAQ CRUD — admin manages the accordion items shown on
 * the public /pricing page. Same simple list+inline-edit shape we
 * use across the other admin lookups.
 */
class PricingFaqController extends Controller
{
    public function index()
    {
        return view('admin.pricing-faqs.index', [
            'faqs' => PricingFaq::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRow($request);
        $data['is_active']  = !empty($request->input('is_active'));
        $data['sort_order'] = $data['sort_order'] ?? (PricingFaq::max('sort_order') + 1);
        PricingFaq::create($data);
        return back()->with('success', 'FAQ created.');
    }

    public function update(Request $request, int $id)
    {
        $faq = PricingFaq::findOrFail($id);
        $data = $this->validateRow($request);
        $data['is_active'] = !empty($request->input('is_active'));
        $faq->update($data);
        return back()->with('success', 'FAQ updated.');
    }

    public function toggle(int $id)
    {
        $faq = PricingFaq::findOrFail($id);
        $faq->update(['is_active' => !$faq->is_active]);
        return back()->with('success', $faq->is_active ? 'Activated.' : 'Deactivated.');
    }

    public function destroy(int $id)
    {
        PricingFaq::findOrFail($id)->delete();
        return back()->with('success', 'FAQ removed.');
    }

    private function validateRow(Request $request): array
    {
        $data = $request->validate([
            'question'   => ['required', 'string', 'max:255'],
            'answer'     => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'placement'  => ['nullable', 'in:pricing,home,both'],
        ]);
        $data['placement'] = $data['placement'] ?? 'pricing';
        return $data;
    }
}

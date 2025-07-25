<?php

namespace App\Http\Controllers;

use App\Models\Risk;
use App\Models\Cause;
use App\Models\SubCause;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RiskController extends Controller
{
    // public function index()
    // {
    //     // Menampilkan semua risk milik user yang sedang login
    //     return Risk::with('causes.subCauses')
    //         ->where('created_by', auth()->id())
    //         ->get();
    // }

    public function index()
    {
        $user = auth()->user();
        $query = Risk::with(['causes.subCauses', 'handlings']);

        if ($user->role !== 'koordinator_mutu') {
            $query->where('created_by', $user->id);
        }
        return $query->get();
    }



    public function store(Request $request)
{
    $request->validate([
        'cluster' => 'required|string',
        'unit' => 'required|string',
        'name' => 'required|string',
        'category' => 'required|string',
        'description' => 'required|string',
        'impact' => 'required|string',
        'uc_c' => 'boolean',
        'causes' => 'required|array',
        'causes.*.category' => 'required|in:man,machine,material,method,environment',
        'causes.*.main_cause' => 'required|string',
        'causes.*.sub_causes' => 'nullable|array',
        'causes.*.sub_causes.*' => 'required|string',
    ]);

    DB::beginTransaction();

    try {
        $risk = Risk::create([
            'id' => Str::uuid(),
            'cluster' => $request->cluster,
            'unit' => $request->unit,
            'name' => $request->name,
            'category' => $request->category,
            'description' => $request->description,
            'impact' => $request->impact,
            'uc_c' => $request->uc_c ?? false,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        foreach ($request->causes as $causeData) {
            $cause = $risk->causes()->create([
                'category' => $causeData['category'],
                'main_cause' => $causeData['main_cause'],
            ]);

            foreach ($causeData['sub_causes'] ?? [] as $sub) {
                $cause->subCauses()->create([
                    'sub_cause' => $sub,
                ]);
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'Risk created successfully',
            'risk' => $risk->load('causes.subCauses')
        ], 201);

    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['error' => 'Failed to create risk', 'message' => $e->getMessage()], 500);
    }
}


    public function show($id)
    {
        $risk = Risk::with('causes.subCauses')
            ->where('id', $id)
            ->where('created_by', auth()->id())
            ->firstOrFail();

        return response()->json($risk);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'cluster' => 'required|string',
            'unit' => 'required|string',
            'name' => 'required|string',
            'category' => 'required|string',
            'description' => 'required|string',
           'impact' => 'required|string',
            'uc_c' => 'boolean',
            'status' => 'string',
            'causes' => 'sometimes|array',
            'causes.*.category' => 'required_with:causes|in:man,machine,material,method,environment',
            'causes.*.main_cause' => 'required_with:causes|string',
            'causes.*.sub_causes' => 'nullable|array',
            'causes.*.sub_causes.*' => 'required_with:causes|string',
        ]);

        $risk = Risk::where('id', $id)
            ->where('created_by', auth()->id())
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $risk->update($request->only([
                'cluster', 'unit', 'name', 'category', 'description', 'impact', 'uc_c', 'status'
            ]));

            // update causes hanya jika dikirim
            if ($request->has('causes')) {
                foreach ($risk->causes as $cause) {
                    $cause->subCauses()->delete();
                }
                $risk->causes()->delete();

                foreach ($request->causes as $causeData) {
                    $cause = $risk->causes()->create([
                        'category' => $causeData['category'],
                        'main_cause' => $causeData['main_cause'],
                    ]);

                    foreach ($causeData['sub_causes'] ?? [] as $sub) {
                        $cause->subCauses()->create([
                            'sub_cause' => $sub,
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'Risk updated successfully',
                'risk' => $risk->load('causes.subCauses')
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update risk',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function destroy($id)
    {
        $risk = Risk::where('id', $id)
            ->where('created_by', auth()->id())
            ->firstOrFail();

        $risk->delete();

        return response()->json(['message' => 'Risk deleted successfully']);
    }
}

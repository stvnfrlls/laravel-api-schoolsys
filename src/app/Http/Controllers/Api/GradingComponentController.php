<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradingComponent;
use Illuminate\Http\Request;

class GradingComponentController extends Controller
{
    public function index(Request $request)
    {
        $query = GradingComponent::with('subject');

        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'weight' => 'required|numeric|min:0|max:100',
            'subject_id' => 'required|exists:subjects,id',
            'is_active' => 'boolean',
        ]);

        // Weights per subject must not exceed 100%
        $totalWeight = GradingComponent::where('subject_id', $validated['subject_id'])
            ->sum('weight');

        if ($totalWeight + $validated['weight'] > 100) {
            return response()->json([
                'message' => 'Total weight for this subject would exceed 100%.',
                'current_total' => $totalWeight,
            ], 422);
        }

        $component = GradingComponent::create($validated);

        return response()->json($component, 201);
    }

    public function show(GradingComponent $gradingComponent)
    {
        return response()->json($gradingComponent->load('subject'));
    }

    public function update(Request $request, GradingComponent $gradingComponent)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:10',
            'weight' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['weight'])) {
            $totalWeight = GradingComponent::where('subject_id', $gradingComponent->subject_id)
                ->where('id', '!=', $gradingComponent->id)
                ->sum('weight');

            if ($totalWeight + $validated['weight'] > 100) {
                return response()->json([
                    'message' => 'Total weight for this subject would exceed 100%.',
                    'current_total' => $totalWeight,
                ], 422);
            }
        }

        $gradingComponent->update($validated);

        return response()->json($gradingComponent);
    }

    public function destroy(GradingComponent $gradingComponent)
    {
        $gradingComponent->delete();
        return response()->json(['message' => 'Grading component deleted.']);
    }
}
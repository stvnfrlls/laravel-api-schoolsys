<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quarter;
use Illuminate\Http\Request;

class QuarterController extends Controller
{
    public function currentQuarter()
    {
        $quarter = Quarter::latest()->first();

        return response()->json($quarter);
    }

    public function updateQuarter(Request $request)
    {
        $request->validate([
            'current_quarter' => 'required|string'
        ]);

        $quarter = Quarter::latest()->first();

        if (!$quarter) {
            $quarter = Quarter::create([
                'current_quarter' => $request->current_quarter
            ]);
        } else {
            $quarter->update([
                'current_quarter' => $request->current_quarter
            ]);
        }

        return response()->json([
            'message' => 'Current quarter updated',
            'data' => $quarter
        ]);
    }
}

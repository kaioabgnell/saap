<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PainelController extends Controller
{
    public function index(Request $request): View
    {
        $learners = $request->user()->learners()
            ->with(['assessments' => fn ($q) => $q->latest('applied_on')])
            ->orderBy('name')
            ->get();

        return view('painel', compact('learners'));
    }
}

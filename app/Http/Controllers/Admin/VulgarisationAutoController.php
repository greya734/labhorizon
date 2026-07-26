<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recherche;
use App\Models\Vulgarisation;
use App\Services\LlmService;
use Illuminate\Http\Request;

class VulgarisationAutoController extends Controller
{
    public function __construct(protected LlmService $llm) {}

    public function create(Recherche $recherche)
    {
        abort_if($recherche->user_id !== auth()->id(), 403);

        if (!$recherche->pdf_path && !$recherche->abstract) {
            return back()->with('error', 'Aucun PDF ni abstract disponible pour cette recherche.');
        }

        return view('admin.vulgarisations.auto', compact('recherche'));
    }

    public function generate(Request $request, Recherche $recherche)
    {
        abort_if($recherche->user_id !== auth()->id(), 403);

        $request->validate([
            'niveau_public' => 'required|in:grand_public,lyceen,collegien',
            'langue'        => 'required|in:fr,en',
        ]);

        // Récupère le texte — PDF en priorité, sinon abstract
        if ($recherche->pdf_path) {
            $texte = $this->llm->extrairePdf($recherche->pdf_path);
        } else {
            $texte = $recherche->abstract;
        }

        if (empty($texte)) {
            return back()->with('error', 'Impossible d\'extraire le contenu.');
        }

        // Génère la vulgarisation
        $resume = $this->llm->vulgariser($texte, $request->niveau_public, $request->langue);

        // Sauvegarde
        $vulgarisation = $recherche->vulgarisations()->create([
            'titre'         => 'Vulgarisation — ' . $request->niveau_public . ' (' . strtoupper($request->langue) . ')',
            'resume'        => $resume,
            'niveau_public' => $request->niveau_public,
            'pdf_path'      => null,
            'langue'        => $request->langue,
        ]);

        return redirect()->route('admin.recherches.show', $recherche)
                         ->with('success', 'Vulgarisation générée avec succès.');
    }
}
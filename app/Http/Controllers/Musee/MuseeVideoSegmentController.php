<?php

namespace App\Http\Controllers\Musee;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseeBloc;
use App\Models\MuseeVideoSegment;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MuseeVideoSegmentController extends Controller
{
    /**
     * Vérifie que le bloc vidéo appartient bien au projet/section indiqués
     * et que l'utilisateur a les droits d'édition. Retourne le ProjetRecherche.
     *
     * @throws HttpException
     */
    private function verifierAcces(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        MuseeBloc $bloc,
    ): ProjetRecherche {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($bloc->section_id !== $section->id, 404);
        abort_if($bloc->type !== MuseeBloc::TYPE_VIDEO, 404);

        $groupe->loadMissing('membres');
        abort_unless(
            $groupe->membres->contains('id', auth()->id()) || $cours->enseignant_id === auth()->id(),
            403,
        );

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        abort_if($bloc->projet_recherche_id !== $projet->id, 404);

        return $projet;
    }

    /**
     * Crée un segment vidéo lié à un bloc et à une section cible.
     *
     * La section_id peut pointer vers n'importe quelle section du type projet,
     * pas nécessairement celle qui contient le bloc vidéo.
     *
     * @throws HttpException
     */
    public function store(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        MuseeBloc $bloc,
    ): RedirectResponse {
        $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section, $bloc);

        $data = $request->validate([
            'section_id' => ['required', 'integer', 'exists:type_projet_sections,id'],
            'debut_secondes' => ['required', 'integer', 'min:0'],
            'fin_secondes' => ['required', 'integer', 'gt:debut_secondes'],
            'label' => ['required', 'string', 'max:255'],
        ]);

        // La section liée doit appartenir au même type de projet
        $sectionCible = TypeProjetSection::findOrFail($data['section_id']);
        abort_if($sectionCible->type_projet_id !== $typeProjet->id, 422);

        MuseeVideoSegment::create([
            'bloc_id' => $bloc->id,
            'section_id' => $data['section_id'],
            'debut_secondes' => $data['debut_secondes'],
            'fin_secondes' => $data['fin_secondes'],
            'label' => $data['label'],
        ]);

        return back()->with('success', 'Segment ajouté.');
    }

    /**
     * Met à jour un segment vidéo existant.
     *
     * @throws HttpException
     */
    public function update(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        MuseeBloc $bloc,
        MuseeVideoSegment $segment,
    ): RedirectResponse {
        $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section, $bloc);
        abort_if($segment->bloc_id !== $bloc->id, 404);

        $data = $request->validate([
            'section_id' => ['sometimes', 'integer', 'exists:type_projet_sections,id'],
            'debut_secondes' => ['sometimes', 'integer', 'min:0'],
            'fin_secondes' => ['sometimes', 'integer', 'min:1'],
            'label' => ['sometimes', 'string', 'max:255'],
        ]);

        if (isset($data['section_id'])) {
            $sectionCible = TypeProjetSection::findOrFail($data['section_id']);
            abort_if($sectionCible->type_projet_id !== $typeProjet->id, 422);
        }

        $segment->update($data);

        return back()->with('success', 'Segment mis à jour.');
    }

    /**
     * Supprime un segment vidéo.
     *
     * @throws HttpException
     */
    public function destroy(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        MuseeBloc $bloc,
        MuseeVideoSegment $segment,
    ): RedirectResponse {
        $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section, $bloc);
        abort_if($segment->bloc_id !== $bloc->id, 404);

        $segment->delete();

        return back()->with('success', 'Segment supprimé.');
    }
}

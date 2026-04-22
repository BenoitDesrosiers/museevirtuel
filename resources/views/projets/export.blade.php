<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $projet->titre_projet ?? 'Projet de recherche' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
        }

        /* ─── Page titre ─────────────────────────────────────── */
        .page-titre {
            page-break-after: always;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 60px 80px;
        }

        .page-titre .auteurs {
            font-size: 12pt;
            margin-bottom: 6pt;
        }

        .page-titre .cours {
            font-size: 12pt;
            margin-bottom: 4pt;
        }

        .page-titre .code {
            font-size: 11pt;
            color: #555;
            margin-bottom: 40pt;
        }

        .page-titre .titre {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8pt;
        }

        .page-titre .type-travail {
            font-size: 12pt;
            color: #333;
            margin-bottom: 40pt;
        }

        .page-titre .presente-a {
            font-size: 12pt;
            margin-bottom: 4pt;
        }

        .page-titre .enseignant {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 20pt;
        }

        .page-titre .departement,
        .page-titre .ecole,
        .page-titre .date {
            font-size: 11pt;
            color: #444;
        }

        /* ─── Table des matières ──────────────────────────────── */
        .toc {
            page-break-after: always;
            padding: 40px 80px;
        }

        .toc h2 {
            text-align: center;
            text-transform: uppercase;
            font-size: 13pt;
            letter-spacing: 1px;
            margin-bottom: 24pt;
        }

        .toc-entry {
            display: flex;
            margin-bottom: 6pt;
            font-size: 11pt;
        }

        .toc-entry .toc-label {
            flex: 1;
        }

        .toc-entry .toc-dots {
            flex: 0 0 auto;
            color: #888;
        }

        /* ─── Sections de contenu ─────────────────────────────── */
        .section {
            page-break-before: always;
            padding: 40px 80px;
        }

        .section h2 {
            font-size: 14pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16pt;
            border-bottom: 1px solid #ccc;
            padding-bottom: 6pt;
        }

        .section h3 {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 8pt;
            margin-top: 12pt;
        }

        .subsection {
            margin-bottom: 20pt;
        }

        .subsection-label {
            font-size: 10pt;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
            margin-bottom: 6pt;
        }

        /* ─── Contenu HTML TipTap ─────────────────────────────── */
        .prose p { margin-bottom: 8pt; }
        .prose ul { margin-left: 20px; margin-bottom: 8pt; list-style-type: disc; }
        .prose ol { margin-left: 20px; margin-bottom: 8pt; list-style-type: decimal; }
        .prose li { margin-bottom: 4pt; }
        .prose strong { font-weight: bold; }
        .prose em { font-style: italic; }
        .prose u { text-decoration: underline; }

        /* ─── Exposants de renvoi ─────────────────────────────── */
        sup.renvoi {
            vertical-align: super;
            font-size: 0.75em;
            font-weight: bold;
            color: #1e40af;
        }

        /* ─── Section Références ──────────────────────────────── */
        .references ol {
            margin-left: 24px;
            list-style-type: decimal;
        }
        .references li {
            margin-bottom: 6pt;
            font-size: 11pt;
        }
    </style>
</head>
<body>

    {{-- ─── Page titre ──────────────────────────────────────────────── --}}
    @if($genererPageTitre)
    <div class="page-titre">
        {{-- Chaque membre sur sa propre ligne --}}
        @foreach($membres as $nom)
            <p class="auteurs">{{ $nom }}</p>
        @endforeach
        <p class="cours">{{ $classe->nom_cours }}</p>
        <p class="code">{{ $classe->code }} / Gr. {{ $classe->groupe }}</p>

        <p class="titre">{{ $projet->titre_projet ?? 'Recherche documentaire' }}</p>
        <p class="type-travail">RECHERCHE DOCUMENTAIRE</p>

        <p class="presente-a">Travail présenté à</p>
        <p class="enseignant">{{ $enseignant->prenom }} {{ $enseignant->nom }}</p>

        <p class="departement">Département des sciences humaines</p>
        <p class="ecole">Cégep de Drummondville</p>
        <p class="date">Le {{ now()->translatedFormat('j F Y') }}</p>
    </div>
    @elseif(!empty($pageTitreContenu))
    {{-- Contenu rédigé manuellement par l'étudiant --}}
    <div class="page-titre">{!! $stripMarks($pageTitreContenu) !!}</div>
    @endif

    {{-- ─── Table des matières ────────────────────────────────────────── --}}
    @if($genererTableMatieres)
    <div class="toc">
        <h2>Table des matières</h2>

        <div class="toc-entry">
            <span class="toc-label">Introduction</span>
            <span class="toc-dots">………… p. 1</span>
        </div>

        @foreach($projet->developpements as $dev)
            <div class="toc-entry">
                <span class="toc-label">
                    {{ $loop->iteration }}. {{ $dev->titre ?: "Paragraphe de développement {$dev->ordre}" }}
                </span>
                <span class="toc-dots">………… p. {{ $loop->iteration + 1 }}</span>
            </div>
        @endforeach

        {{-- Autant d'entrées de conclusion que de membres --}}
        @foreach($membres as $nom)
            <div class="toc-entry">
                <span class="toc-label">Conclusion — {{ $nom }}</span>
                <span class="toc-dots">………… p. {{ $loop->iteration + 7 }}</span>
            </div>
        @endforeach
    </div>
    @elseif(!empty($tableMatieresContenu))
    {{-- Contenu rédigé manuellement par l'étudiant --}}
    <div class="toc">{!! $stripMarks($tableMatieresContenu) !!}</div>
    @endif

    {{-- ─── Sections dynamiques ou Introduction classique ─────────────── --}}
    @if($sections->isNotEmpty())
        @foreach($sections as $section)
            <div class="section">
                <h2>{{ $section['label'] }}</h2>
                @if(!empty($section['contenu']) && trim(strip_tags($section['contenu'])) !== '')
                    <div class="prose">{!! $section['contenu'] !!}</div>
                @else
                    <p style="color: #999; font-style: italic;">(Section non rédigée)</p>
                @endif
            </div>
        @endforeach
    @else
        <div class="section">
            <h2>Introduction</h2>

            @if($projet->introduction_amener)
                <div class="subsection">
                    <p class="subsection-label">Amener</p>
                    <div class="prose">{!! $stripMarks($projet->introduction_amener) !!}</div>
                </div>
            @endif

            @if($projet->introduction_poser)
                <div class="subsection">
                    <p class="subsection-label">Poser</p>
                    <div class="prose">{!! $stripMarks($projet->introduction_poser) !!}</div>
                </div>
            @endif

            @if($projet->introduction_diviser)
                <div class="subsection">
                    <p class="subsection-label">Diviser</p>
                    <div class="prose">{!! $stripMarks($projet->introduction_diviser) !!}</div>
                </div>
            @endif
        </div>
    @endif

    {{-- ─── Paragraphes de développement (dynamiques) ─────────────── --}}
    @foreach($projet->developpements as $dev)
        <div class="section">
            <h2>{{ $dev->titre ?: "Paragraphe de développement {$dev->ordre}" }}</h2>
            @if($dev->contenu && trim(strip_tags($dev->contenu)) !== '')
                <div class="prose">{!! $stripMarks($dev->contenu) !!}</div>
            @else
                <p style="color: #999; font-style: italic;">(Section non rédigée)</p>
            @endif
        </div>
    @endforeach

    {{-- ─── Conclusions individuelles (une par membre) ─────────────── --}}
    @foreach($projet->conclusions->sortBy(fn($c) => $c->user_id) as $conclusion)
        <div class="section">
            <h2>Conclusion — {{ $conclusion->etudiant->prenom }} {{ $conclusion->etudiant->nom }}</h2>
            @if($conclusion->contenu && trim(strip_tags($conclusion->contenu)) !== '')
                <div class="prose">{!! $stripMarks($conclusion->contenu) !!}</div>
            @else
                <p style="color: #999; font-style: italic;">(Section non rédigée)</p>
            @endif
        </div>
    @endforeach

    {{-- Membres sans conclusion --}}
    @foreach($membres as $nomMembre)
        @php
            $aConclusion = $projet->conclusions->contains(fn($c) => "{$c->etudiant->prenom} {$c->etudiant->nom}" === $nomMembre);
        @endphp
        @if(! $aConclusion)
            <div class="section">
                <h2>Conclusion — {{ $nomMembre }}</h2>
                <p style="color: #999; font-style: italic;">(Section non rédigée)</p>
            </div>
        @endif
    @endforeach

    {{-- ─── Références (renvois / endnotes) ───────────────────── --}}
    @if(isset($renvois) && $renvois->isNotEmpty())
    <div class="section references">
        <h2>Références</h2>
        <ol>
            @foreach($renvois as $renvoi)
                <li>{{ $renvoi->contenu ?? '—' }}</li>
            @endforeach
        </ol>
    </div>
    @endif

</body>
</html>

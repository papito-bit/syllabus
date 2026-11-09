@php
    $code = ($syllabus->ec?->code && ! preg_match('/^EC\d+-\d+$/', $syllabus->ec->code)) ? $syllabus->ec->code : '';
    $titre = trim($code.' '.($code ? ': ' : '').$syllabus->ec?->intitule);
    $rubriques = [
        'Objectifs' => $syllabus->objectifs,
        'Contenu' => $syllabus->programme,
        'Bibliographie' => $syllabus->bibliographie,
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Syllabus - {{ $syllabus->ec?->intitule }}</title>
    <style>
        @page { margin: 40px 45px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #0b1733; line-height: 1.55; }
        .entete { width: 100%; border-bottom: 3px solid #d9091a; margin-bottom: 18px; }
        .entete td { padding: 0 0 8px; vertical-align: middle; }
        .entete h1 { margin: 0; font-size: 16px; color: #082f78; text-transform: uppercase; }
        .entete p { margin: 3px 0 0; font-size: 10px; color: #46567a; }
        .titre { font-size: 15px; font-weight: bold; color: #d9091a; text-align: center; margin-bottom: 18px; }
        .rubrique { margin-bottom: 14px; }
        .rubrique__titre { font-size: 11.5px; font-weight: bold; color: #082f78; background: #dbe6ff; padding: 5px 8px; border-left: 4px solid #1550d6; margin-bottom: 6px; }
        .vide { color: #888; font-style: italic; }
    </style>
</head>
<body>
    <table class="entete"><tr>
        <td style="width: 70px">@if($logo)<img src="{{ $logo }}" style="height: 48px" alt="IST-D">@endif</td>
        <td>
            <h1>Syllabus</h1>
            <p>Année universitaire {{ $syllabus->anneeUniversitaire?->libelle }}</p>
        </td>
    </tr></table>

    <div class="titre">{{ $titre }}</div>

    @foreach($rubriques as $nom => $texte)
        <div class="rubrique">
            <div class="rubrique__titre">{{ $nom }}</div>
            <div>{!! $texte ? nl2br(e($texte)) : '<span class="vide">Non renseigné.</span>' !!}</div>
        </div>
    @endforeach
</body>
</html>

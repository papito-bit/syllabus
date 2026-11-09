@php
    // Présentation identique à l'écran : « Autres » avant Total, les autres colonnes après.
    $n = fn ($v) => ($v === null || $v === '') ? '' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $codeReel = fn ($c) => ($c && ! preg_match('/^EC\d+-\d+$/', $c)) ? $c : ''; // codes provisoires jamais affichés
    $colonnes = $maquette->colonnes->sortBy('ordre')->values();
    $avant = $colonnes->filter(fn ($c) => preg_match('/^autre/i', $c->cle))->values();
    $apres = $colonnes->reject(fn ($c) => preg_match('/^autre/i', $c->cle))->values();
    $lignes = $maquette->unites->flatMap(fn ($u) => $u->lignes);
    $avecCode = $lignes->contains(fn ($l) => $codeReel($l->ec?->code));
    $avecCredits = $lignes->contains(fn ($l) => (float) $l->credits > 0);
    $nbVh = 4 + $colonnes->count();
    $groupes = $maquette->unites->groupBy('semestre_id')->sortBy(fn ($u) => $u->first()->semestre?->ordre);
    $val = fn ($l, $c) => data_get($l->donnees_supplementaires, $c->cle);
    $somme = fn ($u, $k) => $u->lignes->sum(fn ($l) => (float) $l->$k);
    $sommeX = function ($u, $c) use ($val) {
        $v = $u->lignes->map(fn ($l) => $val($l, $c))->filter(fn ($x) => $x !== null && $x !== '' && is_numeric($x));
        return $v->isEmpty() ? null : $v->sum(fn ($x) => (float) $x);
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Maquette pédagogique</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #0b1733; }
        .entete { width: 100%; border-bottom: 3px solid #d9091a; margin-bottom: 8px; }
        .entete td { border: 0; padding: 0 0 6px; vertical-align: middle; }
        .entete h1 { margin: 0; font-size: 15px; color: #082f78; text-transform: uppercase; }
        .ref { font-size: 9.5px; color: #46567a; margin-top: 3px; }
        .semestre { font-size: 11px; font-weight: bold; color: #082f78; margin: 10px 0 4px; }
        .ue { page-break-inside: avoid; margin-bottom: 10px; }
        .ue h3 { font-size: 9.5px; margin: 0 0 3px; color: #d9091a; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t th, table.t td { border: 1px solid #7d8db0; padding: 3px 4px; }
        table.t th { background: #dbe6ff; color: #082f78; text-align: center; }
        table.t td.c { text-align: center; }
        table.t tfoot th { background: #ffe0e3; color: #0b1733; }
        .gauche { text-align: left !important; }
    </style>
</head>
<body>
    <table class="entete"><tr>
        <td style="width: 70px">@if($logo)<img src="{{ $logo }}" style="height: 48px" alt="IST-D">@endif</td>
        <td>
            <h1>Maquette pédagogique</h1>
            <div class="ref">
                {{ $maquette->niveau?->libelle }} · {{ $maquette->parcours?->nom }} · Année universitaire {{ $maquette->anneeUniversitaire?->libelle }}
                @if($maquette->version > 1) · Version {{ $maquette->version }} @endif
            </div>
        </td>
    </tr></table>

    @foreach($groupes as $unites)
        <div class="semestre">Parcours : {{ $maquette->parcours?->nom }} ; Semestre : {{ $unites->first()->semestre?->libelle ?: $unites->first()->semestre?->code }}</div>

        @foreach($unites->sortBy('ordre') as $ue)
            <div class="ue">
                <h3>{{ $ue->code }} : {{ $ue->intitule }}</h3>
                <table class="t">
                    <thead>
                        <tr>
                            <th rowspan="2" class="gauche">Matières (EC)</th>
                            @if($avecCode)<th rowspan="2">Code EC</th>@endif
                            @if($avecCredits)<th rowspan="2">Crédits EC</th>@endif
                            <th colspan="{{ $nbVh }}">Volume horaire (VH)</th>
                        </tr>
                        <tr>
                            <th>Cours</th><th>TD</th><th>TP</th>
                            @foreach($avant as $c)<th>{{ $c->libelle }}</th>@endforeach
                            <th>Total</th>
                            @foreach($apres as $c)<th>{{ $c->libelle }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ue->lignes->sortBy('ordre') as $l)
                            <tr>
                                <td>{{ $l->ec?->intitule }}</td>
                                @if($avecCode)<td class="c">{{ $codeReel($l->ec?->code) }}</td>@endif
                                @if($avecCredits)<td class="c">{{ $n($l->credits) }}</td>@endif
                                <td class="c">{{ $n($l->heures_cm) }}</td>
                                <td class="c">{{ $n($l->heures_td) }}</td>
                                <td class="c">{{ $n($l->heures_tp) }}</td>
                                @foreach($avant as $c)<td class="c">{{ $val($l, $c) }}</td>@endforeach
                                <td class="c">{{ $n($l->heures_total) }}</td>
                                @foreach($apres as $c)<td class="c">{{ $n($val($l, $c)) ?: $val($l, $c) }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="gauche">Total</th>
                            @if($avecCode)<th></th>@endif
                            @if($avecCredits)<th>{{ $n($somme($ue, 'credits')) }}</th>@endif
                            <th>{{ $n($somme($ue, 'heures_cm')) }}</th>
                            <th>{{ $n($somme($ue, 'heures_td')) }}</th>
                            <th>{{ $n($somme($ue, 'heures_tp')) }}</th>
                            @foreach($avant as $c)<th>{{ $n($sommeX($ue, $c)) }}</th>@endforeach
                            <th>{{ $n($somme($ue, 'heures_total')) }}</th>
                            @foreach($apres as $c)<th>{{ $n($sommeX($ue, $c)) }}</th>@endforeach
                        </tr>
                        <tr>
                            <th class="gauche">Crédits</th>
                            @if($avecCode)<th></th>@endif
                            @if($avecCredits)<th></th>@endif
                            <th>{{ $n($ue->credits ?? $somme($ue, 'credits')) }}</th>
                            <th colspan="{{ $nbVh - 1 }}"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endforeach
    @endforeach
</body>
</html>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { http, unwrap, liste, erreur, notifier, telecharger, lib, idDe, STATUT, statutDe } from '../api';
const niveaux = ref([]); const parcours = ref([]); const maquette = ref(null); const syllabus = ref(null);
const niveau = ref(null); const parc = ref(null); const busy = ref(false); const err = ref('');
const etape = computed(() => (syllabus.value ? 4 : maquette.value || (parc.value && !busy.value) ? 3 : niveau.value ? 2 : 1));

async function appel(f) { busy.value = true; err.value = ''; try { await f(); } catch (e) { err.value = e.response?.status === 404 ? '' : erreur(e); } finally { busy.value = false; } }
// Index de tous les parcours (pour la recherche rapide) : chargé en arrière-plan.
const index = ref([]); const rech = ref('');
async function indexer() {
    try {
        const parNiveau = await Promise.all(niveaux.value.map(async (nv) => liste(unwrap(await http.get(`/niveaux/${idDe(nv)}/parcours`))).map((p) => ({ niveau: nv, parcours: p }))));
        index.value = parNiveau.flat();
    } catch { /* la recherche reste simplement vide */ }
}
onMounted(() => appel(async () => { niveaux.value = liste(unwrap(await http.get('/niveaux'))); }).then(indexer));
const sans = (t) => String(t ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const resultats = computed(() => {
    const q = sans(rech.value).trim();
    if (q.length < 2) return [];
    return index.value.filter((x) => sans(`${lib(x.parcours)} ${x.parcours.code ?? ''} ${lib(x.niveau)}`).includes(q)).slice(0, 8);
});
const ouvrirResultat = (x) => { rech.value = ''; niveau.value = x.niveau; parcours.value = []; return choisirParcours(x.parcours); };
const choisirNiveau = (n) => appel(async () => { niveau.value = n; parcours.value = liste(unwrap(await http.get(`/niveaux/${idDe(n)}/parcours`))); });
const choisirParcours = (p) => appel(async () => { parc.value = p; maquette.value = null; maquette.value = unwrap(await http.get(`/niveaux/${idDe(niveau.value)}/parcours/${idDe(p)}/maquette`)); });
const ouvrirSyllabus = (id) => id && appel(async () => { syllabus.value = unwrap(await http.get(`/syllabus/${id}`)); });
// Fil d'Ariane : retour direct à n'importe quelle étape.
function versNiveaux() { err.value = ''; syllabus.value = null; maquette.value = null; parc.value = null; niveau.value = null; }
function versParcours() { err.value = ''; syllabus.value = null; maquette.value = null; parc.value = null; if (!parcours.value.length) return choisirNiveau(niveau.value); }
function retour() {
    err.value = '';
    if (syllabus.value) syllabus.value = null;
    else if (parc.value) { parc.value = null; maquette.value = null; }
    else niveau.value = null;
}

const extra = computed(() => maquette.value?.colonnes ?? []);
const n = (v) => (v == null || v === '' ? '' : String(Math.round(Number(v) * 100) / 100).replace('.', ','));
const somme = (u, k) => n((u.lignes ?? []).reduce((a, l) => a + (Number(l[k]) || 0), 0));
const rubriques = computed(() => [['Objectifs', syllabus.value?.objectifs], ['Contenu', syllabus.value?.programme], ['Bibliographie', syllabus.value?.bibliographie]].filter(([, v]) => v));
// Présentation fidèle à la maquette : « Autres » avant Total, les autres colonnes (ex. Travail personnel) après.
const lignes = computed(() => (maquette.value?.semestres ?? []).flatMap((g) => g.unites.flatMap((u) => u.lignes ?? [])));
const avant = computed(() => extra.value.filter((c) => /^autre/i.test(c.cle)));
const apres = computed(() => extra.value.filter((c) => !/^autre/i.test(c.cle)));
const PROVISOIRE = /^EC\d+-\d+$/; // codes d'EC provisoires : jamais affichés, le document n'en fournit pas
const codeReel = (c) => (c && !PROVISOIRE.test(c) ? c : '');
const avecCode = computed(() => lignes.value.some((l) => codeReel(l.ec?.code)));
const avecCredits = computed(() => lignes.value.some((l) => Number(l.credits) > 0));
const nbVh = computed(() => 4 + extra.value.length);
const val = (l, c) => l.donnees_supplementaires?.[c.cle];
const sommeX = (u, c) => { const v = (u.lignes ?? []).map((l) => val(l, c)).filter((x) => x != null && x !== '' && !isNaN(Number(x))); return v.length ? n(v.reduce((a, x) => a + Number(x), 0)) : ''; };
const creditsUe = (u) => (u.credits != null ? n(u.credits) : somme(u, 'credits'));
const titreEc = computed(() => [codeReel(syllabus.value?.ec?.code), syllabus.value?.ec?.intitule].filter(Boolean).join(' : '));
const aide = ['', 'Consultez les maquettes pédagogiques et les syllabus en quelques clics. Commencez par choisir votre niveau d’études : les parcours proposés s’afficheront ensuite.',
    'Choisissez le parcours qui vous intéresse pour afficher sa maquette pédagogique.',
    'Voici la maquette : les unités d’enseignement (UE) par semestre, avec les matières (EC), les volumes horaires et les crédits. Cliquez sur une matière pour lire son syllabus.',
    'Le syllabus décrit les objectifs, le contenu et la bibliographie de la matière.'];
const imprimer = () => window.print();
// PDF généré par Laravel (même mise en page que l'écran, avec le logo).
async function exporterPdf() {
    try { await telecharger(syllabus.value ? `/syllabus/${syllabus.value.id}/pdf` : `/maquettes/${maquette.value.id}/pdf`); }
    catch { notifier('Impossible de générer le PDF. Réessayez, ou utilisez « Imprimer ».', 'error'); }
}
const titreMaquette = computed(() => maquette.value?.titre || [lib(niveau.value), lib(parc.value), maquette.value?.annee_universitaire?.libelle].filter(Boolean).join(' · '));
</script>
<template>
  <div class="row"><button v-if="etape > 1" @click="retour">← Retour</button>
    <span v-if="maquette || syllabus" class="row" style="order:2"><button @click="imprimer">Imprimer</button><button class="primary" @click="exporterPdf">Télécharger le PDF</button></span>
    <h1 class="grow">{{ ['', 'Choisissez un niveau d’études', 'Choisissez un parcours', 'Maquette pédagogique', 'Syllabus'][etape] }}</h1></div>
  <nav v-if="niveau" class="ariane no-print" aria-label="Fil d’Ariane">
    <button @click="versNiveaux">Niveaux</button><span>›</span>
    <button v-if="parc" @click="versParcours">{{ lib(niveau) }}</button><strong v-else>{{ lib(niveau) }}</strong>
    <template v-if="parc"><span>›</span><button v-if="syllabus" @click="syllabus = null">{{ lib(parc) }}</button><strong v-else>{{ lib(parc) }}</strong></template>
    <template v-if="syllabus"><span>›</span><strong>{{ titreEc || 'Syllabus' }}</strong></template>
  </nav>
  <p v-if="niveau" class="mut print-seul">{{ lib(niveau) }}<span v-if="parc"> › {{ lib(parc) }}</span></p>
  <p class="mut aide no-print">Étape {{ etape }} sur 4 — {{ aide[etape] }}</p>
  <p v-if="err" class="err" role="alert">{{ err }}</p><p v-if="busy" class="mut">Chargement…</p>

  <div v-if="etape === 1 && !busy" class="recherche no-print">
    <label for="rech" class="sr">Rechercher un parcours</label>
    <input id="rech" v-model="rech" type="search" placeholder="Rechercher un parcours (par exemple BAT, Réseaux…)" autocomplete="off">
    <ul v-if="resultats.length" class="resultats glass">
      <li v-for="x in resultats" :key="idDe(x.niveau) + idDe(x.parcours)"><button @click="ouvrirResultat(x)"><strong>{{ lib(x.parcours) }}</strong> <span class="mut">{{ lib(x.niveau) }}</span></button></li>
    </ul>
    <p v-else-if="rech.trim().length >= 2 && index.length" class="mut">Aucun parcours trouvé.</p>
  </div>
  <div v-if="etape === 1 && !busy" class="grid">
    <button v-for="x in niveaux" :key="idDe(x)" class="card glass" @click="choisirNiveau(x)"><h3>{{ lib(x) }}</h3><span class="mut">{{ x.code }}</span></button>
    <p v-if="!niveaux.length && !err" class="mut">Aucun niveau d’études n’est disponible pour le moment.</p>
  </div>
  <div v-else-if="etape === 2 && !busy" class="grid">
    <button v-for="p in parcours" :key="idDe(p)" class="card glass" @click="choisirParcours(p)"><h3>{{ lib(p) }}</h3><span class="mut">{{ p.code }}</span></button>
    <p v-if="!parcours.length" class="mut">Aucun parcours n’est disponible pour ce niveau.</p>
  </div>
  <div v-else-if="etape === 3 && !busy">
    <p v-if="!maquette" class="mut">Aucune maquette publiée n’est disponible pour ce parcours.</p>
    <div v-else class="imp-paysage">
      <div class="print-only"><img class="logo" src="/logo-istd.jpg" alt="IST-D"><div><strong>Maquette pédagogique</strong><br>{{ titreMaquette }}</div></div>
      <p class="row mut"><span v-if="maquette.titre">{{ maquette.titre }}</span><span v-if="maquette.annee_universitaire">Année {{ maquette.annee_universitaire.libelle }}</span><span>Version {{ maquette.version }}</span><span class="badge" :class="statutDe(maquette)">{{ maquette.statut_libelle ?? STATUT[statutDe(maquette)] }}</span></p>
      <p v-if="!maquette.semestres?.length" class="mut">Cette maquette ne contient pas encore d’unités d’enseignement.</p>
      <template v-for="g in maquette.semestres" :key="idDe(g.semestre)">
        <h2>{{ lib(g.semestre) }}</h2>
        <section v-for="u in g.unites" :key="idDe(u)" class="glass" style="padding:14px;margin:12px 0">
          <h3>{{ u.code }} : {{ u.intitule }}</h3>
          <div class="tw"><table><thead>
            <tr><th rowspan="2">Matières (EC)</th><th v-if="avecCode" rowspan="2">Code EC</th><th v-if="avecCredits" rowspan="2">Crédits EC</th><th :colspan="nbVh">Volume horaire (VH)</th><th rowspan="2" class="no-print"></th></tr>
            <tr><th>Cours</th><th>TD</th><th>TP</th><th v-for="c in avant" :key="c.cle">{{ c.libelle }}</th><th>Total</th><th v-for="c in apres" :key="c.cle">{{ c.libelle }}</th></tr></thead>
            <tbody><tr v-for="l in u.lignes" :key="idDe(l)" :class="{ click: l.syllabus_id }" @click="ouvrirSyllabus(l.syllabus_id)">
              <td>{{ l.ec?.intitule }}</td><td v-if="avecCode">{{ codeReel(l.ec?.code) }}</td><td v-if="avecCredits">{{ n(l.credits) }}</td>
              <td>{{ n(l.heures_cm) }}</td><td>{{ n(l.heures_td) }}</td><td>{{ n(l.heures_tp) }}</td><td v-for="c in avant" :key="c.cle">{{ val(l, c) }}</td><td>{{ n(l.heures_total) }}</td><td v-for="c in apres" :key="c.cle">{{ n(val(l, c)) || val(l, c) }}</td>
              <td class="mut no-print">{{ l.syllabus_id ? 'Syllabus ›' : 'Pas de syllabus' }}</td></tr></tbody>
            <tfoot><tr><th>Total</th><th v-if="avecCode"></th><th v-if="avecCredits">{{ somme(u, 'credits') }}</th><th>{{ somme(u, 'heures_cm') }}</th><th>{{ somme(u, 'heures_td') }}</th><th>{{ somme(u, 'heures_tp') }}</th><th v-for="c in avant" :key="c.cle">{{ sommeX(u, c) }}</th><th>{{ somme(u, 'heures_total') }}</th><th v-for="c in apres" :key="c.cle">{{ sommeX(u, c) }}</th><th class="no-print"></th></tr>
              <tr><th>Crédits</th><th v-if="avecCode"></th><th v-if="avecCredits"></th><th>{{ creditsUe(u) }}</th><th :colspan="nbVh - 1"></th><th class="no-print"></th></tr></tfoot></table></div>
        </section>
      </template>
    </div>
  </div>
  <div v-else-if="etape === 4" class="imp-portrait">
    <div class="print-only"><img class="logo" src="/logo-istd.jpg" alt="IST-D"><div><strong>Syllabus</strong><br>{{ titreEc }}</div></div>
    <h2>{{ titreEc }}</h2>
    <article v-for="[t, v] in rubriques" :key="t" class="glass" style="padding:16px;margin:12px 0"><h3>{{ t }}</h3><p style="white-space:pre-line;max-width:75ch">{{ v }}</p></article>
    <p v-if="!rubriques.length" class="mut">Le contenu de ce syllabus n’est pas encore renseigné.</p>
  </div>
</template>

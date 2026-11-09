<script setup>
import { ref, computed, onMounted } from 'vue';
import { http, unwrap, liste, erreur, notifier, confirmer, estAdmin, lib, idDe, statutDe, STATUT } from '../api';
const admin = estAdmin();
const items = ref([]); const q = ref(''); const filtre = ref(''); const busy = ref(false); const ed = ref(null);
const niveaux = ref([]); const parcours = ref([]); const annees = ref([]); const semestres = ref([]); const ecs = ref([]);
const nv = ref({ niveau: '', parcours: '', annee: '', titre: '' }); const dup = ref(null);
const ue = ref({ semestre: '', code: '', intitule: '', credits: '' }); const col = ref({ cle: '', libelle: '' }); const nec = ref({ code: '', intitule: '' });
const nl = ref({}); // formulaire d'ajout d'une ligne, par UE

const garde = (f) => async (...a) => { busy.value = true; try { await f(...a); } catch (e) { notifier(erreur(e), 'error'); } finally { busy.value = false; } };
const recharger = garde(async () => { items.value = liste(unwrap(await http.get('/maquettes'))); });
onMounted(async () => {
    recharger();
    garde(async () => { niveaux.value = liste(unwrap(await http.get('/niveaux'))); annees.value = liste(unwrap(await http.get('/annees')));
        if (admin) { semestres.value = liste(unwrap(await http.get('/admin/semestres'))); ecs.value = liste(unwrap(await http.get('/admin/ecs'))); } })();
});
const titre = (m) => m.titre || [lib(m.niveau), lib(m.parcours), m.annee_universitaire?.libelle].filter(Boolean).join(' · ');
const vues = computed(() => items.value.filter((m) => (!filtre.value || statutDe(m) === filtre.value) && titre(m).toLowerCase().includes(q.value.toLowerCase())));
const choisirNiveau = garde(async () => { nv.value.parcours = ''; parcours.value = liste(unwrap(await http.get(`/niveaux/${nv.value.niveau}/parcours`))); });
const creer = garde(async () => {
    const corps = { ...nv.value }; if (!corps.titre) delete corps.titre;
    const m = unwrap(await http.post('/admin/maquettes', corps)); notifier('Maquette créée en brouillon.'); nv.value = { niveau: '', parcours: '', annee: '', titre: '' }; await recharger(); await ouvrir(m);
});

async function action(m, chemin, texte, ok, corps = {}) {
    if (!(await confirmer(texte))) return;
    await garde(async () => { await http.post(`/admin/maquettes/${idDe(m)}/${chemin}`, corps); notifier(ok); dup.value = null; await recharger(); })();
}
const publier = (m) => action(m, 'publier', `Publier « ${titre(m)} » ? Elle deviendra visible du public. Si une version publiée existe déjà pour ce parcours et cette année, elle sera remplacée.`, 'Maquette publiée.', { confirmation: true, confirmer: true });
const archiver = (m) => action(m, 'archiver', `Archiver « ${titre(m)} » ? Elle ne sera plus visible du public.`, 'Maquette archivée.');
const dupliquer = () => action(dup.value.m, 'dupliquer', 'Dupliquer cette maquette vers l’année choisie ?', 'Maquette dupliquée en brouillon.', { annee: dup.value.annee });
async function supprimer(m) {
    if (!(await confirmer(`Supprimer définitivement le brouillon « ${titre(m)} » et tout son contenu ?`))) return;
    await garde(async () => { await http.delete(`/admin/maquettes/${idDe(m)}`); notifier('Brouillon supprimé.'); await recharger(); })();
}

// --- Éditeur de contenu (brouillon uniquement) ---
const charge = async (m) => {
    const d = unwrap(await http.get(`/maquettes/${idDe(m)}`));
    (d.semestres ?? []).forEach((g) => g.unites.forEach((u) => (u.lignes ?? []).forEach((l) => (l.donnees_supplementaires ??= {}))));
    ed.value = d;
};
const ouvrir = garde(charge);
const maj = garde(async () => { await charge(ed.value); });
const tot = (l) => ['heures_cm', 'heures_td', 'heures_tp'].reduce((a, k) => a + (Number(l[k]) || 0), 0);
const ajouterUe = garde(async () => { await http.post(`/admin/maquettes/${idDe(ed.value)}/unites`, ue.value); notifier('UE ajoutée.'); ue.value = { semestre: ue.value.semestre, code: '', intitule: '', credits: '' }; await charge(ed.value); });
async function supprimerUe(u) {
    if (!(await confirmer(`Supprimer l’UE ${u.code} et toutes ses lignes ?`))) return;
    await garde(async () => { await http.delete(`/admin/unites/${idDe(u)}`); notifier('UE supprimée.'); await charge(ed.value); })();
}
const ajouterLigne = garde(async (u) => {
    const f = nl.value[idDe(u)] ?? {};
    await http.post(`/admin/unites/${idDe(u)}/lignes`, { ec: f.ec, credits: f.credits, heures_cm: f.cm, heures_td: f.td, heures_tp: f.ep, heures_total: tot({ heures_cm: f.cm, heures_td: f.td, heures_tp: f.ep }) });
    notifier('EC ajouté.'); nl.value[idDe(u)] = {}; await charge(ed.value);
});
const majLigne = garde(async (l) => {
    await http.put(`/admin/lignes/${idDe(l)}`, { credits: l.credits, heures_cm: l.heures_cm, heures_td: l.heures_td, heures_tp: l.heures_tp, heures_total: tot(l), donnees_supplementaires: l.donnees_supplementaires });
    l.heures_total = tot(l); notifier('Ligne enregistrée.');
});
async function retirerLigne(l) {
    if (!(await confirmer(`Retirer « ${l.ec?.intitule} » de cette UE ?`))) return;
    await garde(async () => { await http.delete(`/admin/lignes/${idDe(l)}`); notifier('EC retiré.'); await charge(ed.value); })();
}
const creerEc = garde(async () => { await http.post('/admin/ecs', nec.value); notifier('EC créé.'); nec.value = { code: '', intitule: '' }; ecs.value = liste(unwrap(await http.get('/admin/ecs'))); });
const colonnes = (lst) => garde(async () => { await http.put(`/admin/maquettes/${idDe(ed.value)}/colonnes`, { colonnes: lst }); notifier('Colonnes enregistrées.'); col.value = { cle: '', libelle: '' }; await charge(ed.value); })();
const ajouterCol = () => colonnes([...(ed.value.colonnes ?? []).map(({ cle, libelle }) => ({ cle, libelle })), { ...col.value }]);
const retirerCol = async (c) => { if (await confirmer(`Retirer la colonne « ${c.libelle} » et ses valeurs ?`)) colonnes(ed.value.colonnes.filter((x) => x.cle !== c.cle).map(({ cle, libelle }) => ({ cle, libelle }))); };
const sauverTitre = garde(async () => { await http.put(`/admin/maquettes/${idDe(ed.value)}`, { titre: ed.value.titre, notes: ed.value.notes ?? null }); notifier('Maquette enregistrée.'); });
</script>
<template>
  <section v-if="!ed">
    <form v-if="admin" class="glass row" style="padding:14px;margin-bottom:14px" @submit.prevent="creer">
      <div class="grow"><label for="mn">Niveau</label><select id="mn" v-model="nv.niveau" required @change="choisirNiveau"><option value="" disabled>Choisir</option><option v-for="n in niveaux" :key="idDe(n)" :value="idDe(n)">{{ lib(n) }}</option></select></div>
      <div class="grow"><label for="mp">Parcours</label><select id="mp" v-model="nv.parcours" required :disabled="!nv.niveau"><option value="" disabled>Choisir</option><option v-for="p in parcours" :key="idDe(p)" :value="idDe(p)">{{ lib(p) }}</option></select></div>
      <div class="grow"><label for="ma">Année</label><select id="ma" v-model="nv.annee" required><option value="" disabled>Choisir</option><option v-for="a in annees" :key="idDe(a)" :value="idDe(a)">{{ a.libelle }}</option></select></div>
      <div class="grow"><label for="mt">Titre (facultatif)</label><input id="mt" v-model="nv.titre"></div>
      <button class="primary" style="align-self:end" :disabled="busy">Créer une maquette</button></form>
    <div class="row"><input v-model="q" class="grow" placeholder="Rechercher une maquette" aria-label="Rechercher">
      <select v-model="filtre" aria-label="Filtrer par statut" style="width:auto"><option value="">Tous les statuts</option><option v-for="(l, k) in STATUT" :key="k" :value="k">{{ l }}</option></select></div>
    <p v-if="!admin" class="mut">Les maquettes sont en lecture seule pour votre rôle.</p>
    <div class="tw"><table><thead><tr><th>Maquette</th><th>Version</th><th>Statut</th><th v-if="admin">Actions</th></tr></thead><tbody>
      <tr v-for="m in vues" :key="idDe(m)"><td>{{ titre(m) }}</td><td>{{ m.version }}</td><td><span class="badge" :class="statutDe(m)">{{ m.statut_libelle ?? STATUT[statutDe(m)] }}</span></td>
        <td v-if="admin"><div class="row"><button :disabled="busy" @click="ouvrir(m)">{{ statutDe(m) === 'brouillon' ? 'Modifier' : 'Consulter' }}</button>
          <button v-if="statutDe(m) === 'brouillon'" class="primary" :disabled="busy" @click="publier(m)">Publier</button>
          <button v-if="statutDe(m) === 'publie'" :disabled="busy" @click="archiver(m)">Archiver</button>
          <button :disabled="busy" @click="dup = { m, annee: '' }">Dupliquer</button>
          <button v-if="statutDe(m) === 'brouillon'" class="danger" :disabled="busy" @click="supprimer(m)">Supprimer</button></div></td></tr>
      <tr v-if="!vues.length"><td colspan="4" class="mut">Aucune maquette ne correspond.</td></tr></tbody></table></div>
    <form v-if="dup" class="glass row" style="padding:14px;margin-top:14px" @submit.prevent="dupliquer"><span class="grow">Dupliquer « {{ titre(dup.m) }} » vers :</span>
      <select v-model="dup.annee" required aria-label="Année cible"><option value="" disabled>Année</option><option v-for="a in annees" :key="idDe(a)" :value="idDe(a)">{{ a.libelle }}</option></select>
      <button class="primary">Dupliquer</button><button type="button" @click="dup = null">Annuler</button></form>
  </section>

  <section v-else>
    <div class="row"><button @click="ed = null; recharger()">← Retour</button><h2 class="grow">{{ titre(ed) }}</h2><span class="badge" :class="statutDe(ed)">{{ ed.statut_libelle }}</span></div>
    <p v-if="statutDe(ed) !== 'brouillon'" class="mut">Cette maquette n’est pas un brouillon : elle est en lecture seule. Dupliquez-la pour la modifier.</p>
    <template v-else>
      <form class="glass row" style="padding:14px" @submit.prevent="sauverTitre"><div class="grow"><label for="et">Titre</label><input id="et" v-model="ed.titre"></div><button class="primary" style="align-self:end">Enregistrer le titre</button></form>
      <div class="glass" style="padding:14px;margin:12px 0"><h3>Colonnes supplémentaires</h3>
        <p class="row"><span v-for="c in ed.colonnes" :key="c.cle" class="badge">{{ c.libelle }} <button style="padding:0 .4em" aria-label="Retirer la colonne" @click="retirerCol(c)">×</button></span><span v-if="!ed.colonnes?.length" class="mut">Aucune (exemple : TPE).</span></p>
        <form class="row" @submit.prevent="ajouterCol"><input v-model="col.cle" class="grow" placeholder="clé (ex. tpe)" pattern="[a-z][a-z0-9_]*" required aria-label="Clé"><input v-model="col.libelle" class="grow" placeholder="Titre (ex. TPE)" required aria-label="Titre"><button>Ajouter la colonne</button></form></div>
      <form class="glass row" style="padding:14px;margin:12px 0" @submit.prevent="ajouterUe"><h3 style="width:100%">Ajouter une UE</h3>
        <select v-model="ue.semestre" required aria-label="Semestre"><option value="" disabled>Semestre</option><option v-for="s in semestres" :key="idDe(s)" :value="idDe(s)">{{ lib(s) }}</option></select>
        <input v-model="ue.code" style="width:110px" placeholder="Code (UE01)" required aria-label="Code"><input v-model="ue.intitule" class="grow" placeholder="Intitulé" required aria-label="Intitulé"><input v-model="ue.credits" type="number" step="0.5" style="width:100px" placeholder="Crédits" aria-label="Crédits"><button class="primary">Ajouter</button></form>
      <form class="glass row" style="padding:14px;margin:12px 0" @submit.prevent="creerEc"><h3 style="width:100%">Nouvel EC dans le catalogue</h3>
        <input v-model="nec.code" style="width:150px" placeholder="Code (011HSEE01)" required aria-label="Code EC"><input v-model="nec.intitule" class="grow" placeholder="Intitulé de l’EC" required aria-label="Intitulé EC"><button>Créer l’EC</button></form>
    </template>

    <template v-for="g in ed.semestres" :key="idDe(g.semestre)"><h2>{{ lib(g.semestre) }}</h2>
      <section v-for="u in g.unites" :key="idDe(u)" class="glass" style="padding:14px;margin:12px 0">
        <div class="row"><h3 class="grow">{{ u.code }} : {{ u.intitule }}</h3><button v-if="statutDe(ed) === 'brouillon'" class="danger" @click="supprimerUe(u)">Supprimer l’UE</button></div>
        <div class="tw"><table><thead><tr><th>EC</th><th>Code</th><th>Crédits</th><th>ET</th><th>ED</th><th>EP</th><th>VH</th><th v-for="c in ed.colonnes" :key="c.cle">{{ c.libelle }}</th><th></th></tr></thead><tbody>
          <tr v-for="l in u.lignes" :key="idDe(l)"><td>{{ l.ec?.intitule }}</td><td>{{ l.ec?.code }}</td>
            <template v-if="statutDe(ed) === 'brouillon'"><td v-for="k in ['credits', 'heures_cm', 'heures_td', 'heures_tp']" :key="k"><input v-model="l[k]" type="number" step="0.5" min="0" style="width:80px" :aria-label="k" @change="majLigne(l)"></td><td>{{ tot(l) }}</td>
              <td v-for="c in ed.colonnes" :key="c.cle"><input v-model="l.donnees_supplementaires[c.cle]" style="width:80px" :aria-label="c.libelle" @change="majLigne(l)"></td><td><button class="danger" @click="retirerLigne(l)">Retirer</button></td></template>
            <template v-else><td>{{ l.credits }}</td><td>{{ l.heures_cm }}</td><td>{{ l.heures_td }}</td><td>{{ l.heures_tp }}</td><td>{{ l.heures_total }}</td><td v-for="c in ed.colonnes" :key="c.cle">{{ l.donnees_supplementaires?.[c.cle] }}</td><td></td></template></tr>
          <tr v-if="!u.lignes?.length"><td colspan="9" class="mut">Aucun EC dans cette UE.</td></tr></tbody></table></div>
        <form v-if="statutDe(ed) === 'brouillon'" class="row" style="margin-top:10px" @submit.prevent="ajouterLigne(u)">
          <select v-model="(nl[idDe(u)] ??= {}).ec" class="grow" required aria-label="EC à ajouter"><option :value="undefined" disabled>Ajouter un EC…</option><option v-for="e in ecs" :key="idDe(e)" :value="idDe(e)">{{ e.code }} {{ e.intitule }}</option></select>
          <input v-model="nl[idDe(u)].credits" type="number" step="0.5" style="width:90px" placeholder="Crédits" aria-label="Crédits"><input v-model="nl[idDe(u)].cm" type="number" step="0.5" style="width:80px" placeholder="ET" aria-label="ET"><input v-model="nl[idDe(u)].td" type="number" step="0.5" style="width:80px" placeholder="ED" aria-label="ED"><input v-model="nl[idDe(u)].ep" type="number" step="0.5" style="width:80px" placeholder="EP" aria-label="EP"><button class="primary">Ajouter</button></form>
      </section></template>
  </section>
</template>

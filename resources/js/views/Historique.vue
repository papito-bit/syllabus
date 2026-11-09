<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { http, unwrap, liste, erreur, notifier } from '../api';
const entrees = ref([]); const meta = ref(null); const page = ref(1); const busy = ref(false);
const q = ref(''); const ouvert = ref(null);
// Filtres envoyés au serveur (HistoriqueController) : entite_type, action, user, du, au, per_page.
const f = reactive({ entite_type: '', action: '', user: '', du: '', au: '', per_page: 25 });
const optActions = ref([]); const optTypes = ref([]); const optUsers = ref([]);

const fusion = (a, b) => [...new Set([...a, ...b.filter(Boolean)])].sort();
function apprendre(lst) {
    optActions.value = fusion(optActions.value, lst.map((e) => e.action));
    optTypes.value = fusion(optTypes.value, lst.map((e) => e.entite_type));
    const m = new Map(optUsers.value.map((u) => [u.id, u]));
    lst.forEach((e) => { if (e.utilisateur?.id) m.set(e.utilisateur.id, e.utilisateur); });
    optUsers.value = [...m.values()].sort((a, b) => String(a.name).localeCompare(String(b.name)));
}
async function charger(p = 1) {
    busy.value = true; ouvert.value = null;
    try {
        const params = { page: p, per_page: f.per_page };
        for (const k of ['entite_type', 'action', 'user', 'du', 'au']) if (f[k]) params[k] = f[k];
        const r = await http.get('/admin/historique', { params });
        entrees.value = liste(r.data); meta.value = r.data.meta ?? null; page.value = meta.value?.current_page ?? p;
        apprendre(entrees.value);
    } catch (e) { notifier(erreur(e), 'error'); } finally { busy.value = false; }
}
onMounted(async () => {
    charger();
    // Valeurs possibles des filtres : un aperçu des 100 dernières entrées et la liste des comptes.
    try { apprendre(liste((await http.get('/admin/historique', { params: { per_page: 100 } })).data)); } catch { /* facultatif */ }
    try { const us = liste(unwrap(await http.get('/admin/utilisateurs'))).map((u) => ({ id: u.id, name: u.name })); apprendre(us.map((u) => ({ utilisateur: u }))); } catch { /* facultatif */ }
});
watch(() => [f.entite_type, f.action, f.user, f.du, f.au, f.per_page], () => charger(1));
function reinitialiser() { Object.assign(f, { entite_type: '', action: '', user: '', du: '', au: '' }); q.value = ''; }

const dernier = computed(() => meta.value?.last_page ?? 1);
const vues = computed(() => entrees.value.filter((e) => [e.description, e.action, e.utilisateur?.name, e.entite_type].join(' ').toLowerCase().includes(q.value.toLowerCase())));
const date = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'medium' }) : '');
const ton = (a) => (/cr[eé]|ajout|accord|publi/i.test(a) ? 'publie' : /suppr|retir|revoq|desactiv/i.test(a) ? '' : 'archive');
const cles = (e) => [...new Set([...Object.keys(e.anciennes_valeurs ?? {}), ...Object.keys(e.nouvelles_valeurs ?? {})])];
const aff = (v) => { if (v == null) return '—'; const s = typeof v === 'object' ? JSON.stringify(v) : String(v); return s.length > 160 ? s.slice(0, 160) + '…' : s; };
</script>
<template>
  <p class="mut aide">Journal en lecture seule de toutes les modifications faites dans l’application : qui a fait quoi, sur quel élément et quand. Cliquez sur une ligne pour voir les valeurs avant et après.</p>
  <div class="glass row" style="padding:14px;margin-bottom:12px">
    <div><label for="hu">Utilisateur</label><select id="hu" v-model="f.user"><option value="">Tous</option><option v-for="u in optUsers" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
    <div><label for="ha">Action</label><select id="ha" v-model="f.action"><option value="">Toutes</option><option v-for="a in optActions" :key="a" :value="a">{{ a }}</option></select></div>
    <div><label for="ht">Élément</label><select id="ht" v-model="f.entite_type"><option value="">Tous</option><option v-for="t in optTypes" :key="t" :value="t">{{ t }}</option></select></div>
    <div><label for="hd">Du</label><input id="hd" v-model="f.du" type="date"></div>
    <div><label for="hf">Au</label><input id="hf" v-model="f.au" type="date"></div>
    <div><label for="hp">Par page</label><select id="hp" v-model.number="f.per_page"><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></div>
    <button style="align-self:end" @click="reinitialiser">Réinitialiser</button><button style="align-self:end" :disabled="busy" @click="charger(page)">Actualiser</button>
  </div>
  <input v-model="q" placeholder="Rechercher dans la page affichée (description, utilisateur…)" aria-label="Rechercher dans la page">
  <p v-if="meta" class="mut">{{ meta.total }} entrée(s) correspondent aux filtres.</p>
  <div class="tw"><table><thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Élément</th><th>Description</th></tr></thead><tbody>
    <template v-for="(e, i) in vues" :key="e.created_at + i">
      <tr class="click" tabindex="0" @click="ouvert = ouvert === i ? null : i" @keydown.enter="ouvert = ouvert === i ? null : i">
        <td>{{ date(e.created_at) }}</td><td>{{ e.utilisateur?.name ?? 'Système' }}</td><td><span class="badge" :class="ton(e.action)">{{ e.action }}</span></td>
        <td>{{ e.entite_type }}</td><td style="white-space:normal">{{ e.description }}</td></tr>
      <tr v-if="ouvert === i"><td colspan="5" style="white-space:normal">
        <p class="mut">Identifiant de l’élément : {{ e.entite_id }} · Adresse IP : {{ e.ip_address ?? 'inconnue' }}</p>
        <table v-if="cles(e).length"><thead><tr><th>Champ</th><th>Avant</th><th>Après</th></tr></thead><tbody>
          <tr v-for="c in cles(e)" :key="c"><td>{{ c }}</td><td style="white-space:normal">{{ aff(e.anciennes_valeurs?.[c]) }}</td><td style="white-space:normal">{{ aff(e.nouvelles_valeurs?.[c]) }}</td></tr></tbody></table>
        <p v-else class="mut">Aucun détail de valeurs enregistré pour cette action.</p></td></tr>
    </template>
    <tr v-if="!vues.length"><td colspan="5" class="mut">{{ busy ? 'Chargement…' : 'Aucune entrée à afficher.' }}</td></tr></tbody></table></div>
  <p v-if="meta" class="row" style="margin-top:12px"><button :disabled="page <= 1 || busy" @click="charger(page - 1)">← Précédent</button><span class="mut">Page {{ page }} sur {{ dernier }}</span><button :disabled="page >= dernier || busy" @click="charger(page + 1)">Suivant →</button></p>
</template>
